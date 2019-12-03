<?php

abstract class PhabricatorManagementWorkflow extends PhutilArgumentWorkflow {

  public function isExecutable() {
    return true;
  }

  public function getViewer() {
    // Some day, we might provide a more general viewer mechanism to scripts.
    // For now, workflows can call this method for convenience and future
    // flexibility.
    return PhabricatorUser::getOmnipotentUser();
  }

  protected function parseTimeArgument($time) {
    if (!strlen($time)) {
      return null;
    }

    $epoch = strtotime($time);
    if ($epoch <= 0) {
      throw new PhutilArgumentUsageException(
        pht('Unable to parse time "%s".', $time));
    }
    return $epoch;
  }

  protected function newContentSource() {
    return PhabricatorContentSource::newForSource(
      PhabricatorConsoleContentSource::SOURCECONST);
  }

  protected function logInfo($label, $message) {
    $this->logRaw(
      tsprintf(
        "**<bg:blue> %s </bg>** %s\n",
        $label,
        $message));
  }

  protected function logOkay($label, $message) {
    $this->logRaw(
      tsprintf(
        "**<bg:green> %s </bg>** %s\n",
        $label,
        $message));
  }

  protected function logWarn($label, $message) {
    $this->logRaw(
      tsprintf(
        "**<bg:yellow> %s </bg>** %s\n",
        $label,
        $message));
  }

  protected function logFail($label, $message) {
    $this->logRaw(
      tsprintf(
        "**<bg:red> %s </bg>** %s\n",
        $label,
        $message));
  }

  private function logRaw($message) {
    fprintf(STDERR, '%s', $message);
  }

  final protected function loadUsersFromArguments(array $identifiers) {
    if (!$identifiers) {
      return array();
    }

    $ids = array();
    $phids = array();
    $usernames = array();

    $user_type = PhabricatorPeopleUserPHIDType::TYPECONST;

    foreach ($identifiers as $identifier) {
      // If the value is a user PHID, treat as a PHID.
      if (phid_get_type($identifier) === $user_type) {
        $phids[$identifier] = $identifier;
        continue;
      }

      // If the value is "@..." and then some text, treat it as a username.
      if ((strlen($identifier) > 1) && ($identifier[0] == '@')) {
        $usernames[$identifier] = substr($identifier, 1);
        continue;
      }

      // If the value is digits, treat it as both an ID and a username.
      // Entirely numeric usernames, like "1234", are valid.
      if (ctype_digit($identifier)) {
        $ids[$identifier] = $identifier;
        $usernames[$identifier] = $identifier;
        continue;
      }

      // Otherwise, treat it as an unescaped username.
      $usernames[$identifier] = $identifier;
    }

    $viewer = $this->getViewer();
    $results = array();

    if ($phids) {
      $users = id(new PhabricatorPeopleQuery())
        ->setViewer($viewer)
        ->withPHIDs($phids)
        ->execute();
      foreach ($users as $user) {
        $phid = $user->getPHID();
        $results[$phid][] = $user;
      }
    }

    if ($usernames) {
      $users = id(new PhabricatorPeopleQuery())
        ->setViewer($viewer)
        ->withUsernames($usernames)
        ->execute();

      $reverse_map = array();
      foreach ($usernames as $identifier => $username) {
        $username = phutil_utf8_strtolower($username);
        $reverse_map[$username][] = $identifier;
      }

      foreach ($users as $user) {
        $username = $user->getUsername();
        $username = phutil_utf8_strtolower($username);

        $reverse_identifiers = idx($reverse_map, $username, array());

        if (count($reverse_identifiers) > 1) {
          throw new PhutilArgumentUsageException(
            pht(
              'Multiple user identifiers (%s) correspond to the same user. '.
              'Identify each user exactly once.',
              implode(', ', $reverse_identifiers)));
        }

        foreach ($reverse_identifiers as $reverse_identifier) {
          $results[$reverse_identifier][] = $user;
        }
      }
    }

    if ($ids) {
      $users = id(new PhabricatorPeopleQuery())
        ->setViewer($viewer)
        ->withIDs($ids)
        ->execute();

      foreach ($users as $user) {
        $id = $user->getID();
        $results[$id][] = $user;
      }
    }

    $list = array();
    foreach ($identifiers as $identifier) {
      $users = idx($results, $identifier, array());
      if (!$users) {
        throw new PhutilArgumentUsageException(
          pht(
            'No user "%s" exists. Specify users by username, ID, or PHID.',
            $identifier));
      }

      if (count($users) > 1) {
        // This can happen if you have a user "@25", a user with ID 25, and
        // specify "--user 25". You can disambiguate this by specifying
        // "--user @25".
        throw new PhutilArgumentUsageException(
          pht(
            'Identifier "%s" matches multiple users. Specify each user '.
            'unambiguously with "@username" or by using user PHIDs.',
            $identifier));
      }

      $list[] = head($users);
    }

    return $list;
  }

}
