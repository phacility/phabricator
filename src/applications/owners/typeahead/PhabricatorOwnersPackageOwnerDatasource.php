<?php

final class PhabricatorOwnersPackageOwnerDatasource
  extends PhabricatorTypeaheadCompositeDatasource {

  public function getBrowseTitle() {
    return pht('Browse Packages by Owner');
  }

  public function getPlaceholderText() {
    return pht('Type packages(<user>) or packages(<project>)...');
  }

  public function getDatasourceApplicationClass() {
    return 'PhabricatorOwnersApplication';
  }

  public function getComponentDatasources() {
    return array(
      new PhabricatorPeopleDatasource(),
      new PhabricatorProjectDatasource(),
    );
  }

  public function getDatasourceFunctions() {
    return array(
      'packages' => array(
        'name' => pht('Packages: ...'),
        'arguments' => pht('owner'),
        'summary' => pht("Find results in any of an owner's packages."),
        'description' => pht(
          "This function allows you to find results associated with any ".
          "of the packages a specified user or project is an owner of. ".
          "For example, this will find results associated with all of ".
          "the projects `%s` owns:\n\n%s\n\n",
          'alincoln',
          '> packages(alincoln)'),
      ),
    );
  }

  protected function didLoadResults(array $results) {
    foreach ($results as $result) {
      $result
        ->setColor(null)
        ->setTokenType(PhabricatorTypeaheadTokenView::TYPE_FUNCTION)
        ->setIcon('fa-asterisk')
        ->setPHID('packages('.$result->getPHID().')')
        ->setDisplayName(pht('Packages: %s', $result->getDisplayName()))
        ->setName($result->getName().' packages');
    }

    return $results;
  }

  protected function evaluateFunction($function, array $argv_list) {
    $phids = array();
    foreach ($argv_list as $argv) {
      $phids[] = head($argv);
    }

    $phids = $this->resolvePHIDs($phids);

    $owner_phids = array();
    foreach ($phids as $key => $phid) {
      switch (phid_get_type($phid)) {
        case PhabricatorPeopleUserPHIDType::TYPECONST:
        case PhabricatorProjectProjectPHIDType::TYPECONST:
          $owner_phids[] = $phid;
          unset($phids[$key]);
          break;
      }
    }

    if ($owner_phids) {
      $packages = id(new PhabricatorOwnersPackageQuery())
        ->setViewer($this->getViewer())
        ->withOwnerPHIDs($owner_phids)
        ->execute();
      foreach ($packages as $package) {
        $phids[] = $package->getPHID();
      }
    }

    return $phids;
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
          ->setValue(pht('Packages: Invalid Owner'));
      } else {
        $token
          ->setIcon('fa-asterisk')
          ->setTokenType(PhabricatorTypeaheadTokenView::TYPE_FUNCTION)
          ->setKey('packages('.$token->getKey().')')
          ->setValue(pht('Packages: %s', $token->getValue()));
      }
    }

    return $tokens;
  }

  private function resolvePHIDs(array $phids) {

    // TODO: It would be nice for this to handle `packages(#project)` from a
    // query string or eventually via Conduit. This could also share code with
    // PhabricatorProjectLogicalUserDatasource.

    $usernames = array();
    foreach ($phids as $key => $phid) {
      switch (phid_get_type($phid)) {
        case PhabricatorPeopleUserPHIDType::TYPECONST:
        case PhabricatorProjectProjectPHIDType::TYPECONST:
          break;
        default:
          $usernames[$key] = $phid;
          break;
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
