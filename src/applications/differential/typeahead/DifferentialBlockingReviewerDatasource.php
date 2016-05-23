<?php

final class DifferentialBlockingReviewerDatasource
  extends PhabricatorTypeaheadCompositeDatasource {

  public function getBrowseTitle() {
    return pht('Browse Blocking Reviewers');
  }

  public function getPlaceholderText() {
    return pht('Type a user, project, or package name...');
  }

  public function getDatasourceApplicationClass() {
    return 'PhabricatorDifferentialApplication';
  }

  public function getComponentDatasources() {
    return array(
      new PhabricatorPeopleDatasource(),
      new PhabricatorProjectDatasource(),
      new PhabricatorOwnersPackageDatasource(),
    );
  }

  public function getDatasourceFunctions() {
    return array(
      'blocking' => array(
        'name' => pht('Blocking: ...'),
        'arguments' => pht('reviewer'),
        'summary' => pht('Select a blocking reviewer.'),
        'description' => pht(
          "This function allows you to add a reviewer as a blocking ".
          "reviewer. For example, this will add `%s` as a blocking reviewer: ".
          "\n\n%s\n\n",
          'alincoln',
          '> blocking(alincoln)'),
      ),
    );
  }


  protected function didLoadResults(array $results) {
    foreach ($results as $result) {
      $result
        ->setColor('red')
        ->setTokenType(PhabricatorTypeaheadTokenView::TYPE_FUNCTION)
        ->setIcon('fa-asterisk')
        ->setPHID('blocking('.$result->getPHID().')')
        ->setDisplayName(pht('Blocking: %s', $result->getDisplayName()))
        ->setName($result->getName().' blocking');
    }

    return $results;
  }

  protected function evaluateFunction($function, array $argv_list) {
    $phids = array();
    foreach ($argv_list as $argv) {
      $phids[] = head($argv);
    }

    $phids = $this->resolvePHIDs($phids);

    $results = array();
    foreach ($phids as $phid) {
      $results[] = array(
        'type' => DifferentialReviewerStatus::STATUS_BLOCKING,
        'phid' => $phid,
      );
    }

    return $results;
  }

  public function renderFunctionTokens($function, array $argv_list) {
    $phids = array();
    foreach ($argv_list as $argv) {
      $phids[] = head($argv);
    }

    $phids = $this->resolvePHIDs($phids);

    $tokens = $this->renderTokens($phids);
    foreach ($tokens as $token) {
      $token->setColor(null);
      if ($token->isInvalid()) {
        $token
          ->setValue(pht('Blocking: Invalid Reviewer'));
      } else {
        $token
          ->setIcon('fa-asterisk')
          ->setTokenType(PhabricatorTypeaheadTokenView::TYPE_FUNCTION)
          ->setColor('red')
          ->setKey('blocking('.$token->getKey().')')
          ->setValue(pht('Blocking: %s', $token->getValue()));
      }
    }

    return $tokens;
  }

  private function resolvePHIDs(array $phids) {
    $usernames = array();
    foreach ($phids as $key => $phid) {
      if (phid_get_type($phid) != PhabricatorPeopleUserPHIDType::TYPECONST) {
        $usernames[$key] = $phid;
      }
    }

    if ($usernames) {
      $users = id(new PhabricatorPeopleQuery())
        ->setViewer($this->getViewer())
        ->withUsernames($usernames)
        ->execute();
      $users = mpull($users, null, 'getUsername');
      foreach ($usernames as $key => $username) {
        $user = idx($users, $username);
        if ($user) {
          $phids[$key] = $user->getPHID();
        }
      }
    }

    return $phids;
  }

}
