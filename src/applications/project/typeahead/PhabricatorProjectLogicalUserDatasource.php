<?php

final class PhabricatorProjectLogicalUserDatasource
  extends PhabricatorTypeaheadCompositeDatasource {

  public function getBrowseTitle() {
    return pht('Browse User Projects');
  }

  public function getPlaceholderText() {
    return pht('Type projects(<user>)...');
  }

  public function getDatasourceApplicationClass() {
    return 'PhabricatorProjectApplication';
  }

  public function getComponentDatasources() {
    return array(
      new PhabricatorPeopleDatasource(),
    );
  }

  public function getDatasourceFunctions() {
    return array(
      'projects' => array(
        'name' => pht('Projects: ...'),
        'arguments' => pht('username'),
        'summary' => pht("Find results in any of a user's projects."),
        'description' => pht(
          "This function allows you to find results associated with any ".
          "of the projects a specified user is a member of. For example, ".
          "this will find results associated with all of the projects ".
          "`%s` is a member of:\n\n%s\n\n",
          'alincoln',
          '> projects(alincoln)'),
      ),
    );
  }

  protected function didLoadResults(array $results) {
    foreach ($results as $result) {
      $result
        ->setColor(null)
        ->setTokenType(PhabricatorTypeaheadTokenView::TYPE_FUNCTION)
        ->setIcon('fa-asterisk')
        ->setPHID('projects('.$result->getPHID().')')
        ->setDisplayName(pht("User's Projects: %s", $result->getDisplayName()))
        ->setName('projects '.$result->getName());
    }

    return $results;
  }

  protected function evaluateFunction($function, array $argv_list) {
    $phids = array();
    foreach ($argv_list as $argv) {
      $phids[] = head($argv);
    }

    $phids = $this->resolvePHIDs($phids);

    $projects = id(new PhabricatorProjectQuery())
      ->setViewer($this->getViewer())
      ->withMemberPHIDs($phids)
      ->execute();

    $results = array();
    foreach ($projects as $project) {
      $results[] = new PhabricatorQueryConstraint(
        PhabricatorQueryConstraint::OPERATOR_OR,
        $project->getPHID());
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
          ->setValue(pht("User's Projects: Invalid User"));
      } else {
        $token
          ->setIcon('fa-asterisk')
          ->setTokenType(PhabricatorTypeaheadTokenView::TYPE_FUNCTION)
          ->setKey('projects('.$token->getKey().')')
          ->setValue(pht("User's Projects: %s", $token->getValue()));
      }
    }

    return $tokens;
  }

  private function resolvePHIDs(array $phids) {
    // If we have a function like `projects(alincoln)`, try to resolve the
    // username first. This won't happen normally, but can be passed in from
    // the query string.

    // The user might also give us an invalid username. In this case, we
    // preserve it and return it in-place so we get an "invalid" token rendered
    // in the UI. This shows the user where the issue is and  best represents
    // the user's input.

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
