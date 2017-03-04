<?php

abstract class PhabricatorPeopleManagementWorkflow
  extends PhabricatorManagementWorkflow {

  protected function buildIterator(PhutilArgumentParser $args) {
    $usernames = $args->getArg('users');

    if ($args->getArg('all')) {
      if ($usernames) {
        throw new PhutilArgumentUsageException(
          pht(
            'Specify either a list of users or `%s`, but not both.',
            '--all'));
      }
      return new LiskMigrationIterator(new PhabricatorUser());
    }

    if ($usernames) {
      return $this->loadUsersWithUsernames($usernames);
    }

    return null;
  }

  protected function loadUsersWithUsernames(array $usernames) {
    $users = array();
    foreach($usernames as $username) {
      $query = id(new PhabricatorPeopleQuery())
        ->setViewer($this->getViewer())
        ->withUsernames(array($username))
        ->executeOne();

      if (!$query) {
        throw new PhutilArgumentUsageException(
          pht(
            '"%s" is not a valid username.',
            $username));
      }
      $users[] = $query;
    }

    return $users;
  }


}
