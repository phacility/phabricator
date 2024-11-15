<?php

final class PhabricatorRoleLogicalUserDatasource
  extends PhabricatorTypeaheadCompositeDatasource {

  public function getBrowseTitle() {
    return pht('Browse User Roles');
  }

  public function getPlaceholderText() {
    return pht('Type roles(<user>)...');
  }

  public function getDatasourceApplicationClass() {
    return 'PhabricatorRoleApplication';
  }

  public function getComponentDatasources() {
    return array(
      new PhabricatorPeopleDatasource(),
    );
  }

  public function getDatasourceFunctions() {
    return array(
      'roles' => array(
        'name' => pht('Roles: ...'),
        'arguments' => pht('username'),
        'summary' => pht("Find results in any of a user's roles."),
        'description' => pht(
          "This function allows you to find results associated with any ".
          "of the roles a specified user is a member of. For example, ".
          "this will find results associated with all of the roles ".
          "`%s` is a member of:\n\n%s\n\n",
          'alincoln',
          '> roles(alincoln)'),
      ),
    );
  }

  protected function didLoadResults(array $results) {
    foreach ($results as $result) {
      $result
        ->setColor(null)
        ->setTokenType(PhabricatorTypeaheadTokenView::TYPE_FUNCTION)
        ->setIcon('fa-asterisk')
        ->setPHID('roles('.$result->getPHID().')')
        ->setDisplayName(pht("User's Roles: %s", $result->getDisplayName()))
        ->setName('roles '.$result->getName());
    }

    return $results;
  }

  protected function evaluateFunction($function, array $argv_list) {
    $phids = array();
    foreach ($argv_list as $argv) {
      $phids[] = head($argv);
    }

    $phids = $this->resolvePHIDs($phids);

    $roles = id(new PhabricatorRoleQuery())
      ->setViewer($this->getViewer())
      ->withMemberPHIDs($phids)
      ->execute();

    $results = array();
    foreach ($roles as $role) {
      $results[] = new PhabricatorQueryConstraint(
        PhabricatorQueryConstraint::OPERATOR_OR,
        $role->getPHID());
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
          ->setValue(pht("User's Roles: Invalid User"));
      } else {
        $token
          ->setIcon('fa-asterisk')
          ->setTokenType(PhabricatorTypeaheadTokenView::TYPE_FUNCTION)
          ->setKey('roles('.$token->getKey().')')
          ->setValue(pht("User's Roles: %s", $token->getValue()));
      }
    }

    return $tokens;
  }

  private function resolvePHIDs(array $phids) {
    // If we have a function like `roles(alincoln)`, try to resolve the
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
