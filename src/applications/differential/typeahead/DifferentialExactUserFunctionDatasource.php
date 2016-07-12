<?php

final class DifferentialExactUserFunctionDatasource
  extends PhabricatorTypeaheadCompositeDatasource {

  public function getBrowseTitle() {
    return pht('Browse Users');
  }

  public function getPlaceholderText() {
    return pht('Type exact(<user>)...');
  }

  public function getDatasourceApplicationClass() {
    return 'PhabricatorDifferentialApplication';
  }

  public function getComponentDatasources() {
    return array(
      new PhabricatorPeopleDatasource(),
    );
  }

  public function getDatasourceFunctions() {
    return array(
      'exact' => array(
        'name' => pht('Exact: ...'),
        'arguments' => pht('username'),
        'summary' => pht('Find results matching users exactly.'),
        'description' => pht(
          "This function allows you to find results associated only with ".
          "a user, exactly, and not any of their projects or packages. For ".
          "example, this will find results associated with only `%s`:".
          "\n\n%s\n\n",
          'alincoln',
          '> exact(alincoln)'),
      ),
    );
  }

  protected function didLoadResults(array $results) {
    foreach ($results as $result) {
      $result
        ->setColor(null)
        ->setTokenType(PhabricatorTypeaheadTokenView::TYPE_FUNCTION)
        ->setIcon('fa-asterisk')
        ->setPHID('exact('.$result->getPHID().')')
        ->setDisplayName(pht('Exact User: %s', $result->getDisplayName()))
        ->setName($result->getName().' exact');
    }

    return $results;
  }

  protected function evaluateFunction($function, array $argv_list) {
    $phids = array();
    foreach ($argv_list as $argv) {
      $phids[] = head($argv);
    }

    return $this->resolvePHIDs($phids);
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
          ->setValue(pht('Exact User: Invalid User'));
      } else {
        $token
          ->setIcon('fa-asterisk')
          ->setTokenType(PhabricatorTypeaheadTokenView::TYPE_FUNCTION)
          ->setKey('exact('.$token->getKey().')')
          ->setValue(pht('Exact User: %s', $token->getValue()));
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
