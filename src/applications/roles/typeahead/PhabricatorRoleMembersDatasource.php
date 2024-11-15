<?php

final class PhabricatorRoleMembersDatasource
  extends PhabricatorTypeaheadCompositeDatasource {

  public function getBrowseTitle() {
    return pht('Browse Members');
  }

  public function getPlaceholderText() {
    return pht('Type members(<role>)...');
  }

  public function getDatasourceApplicationClass() {
    return 'PhabricatorRoleApplication';
  }

  public function getComponentDatasources() {
    return array(
      new PhabricatorRoleDatasource(),
    );
  }

  public function getDatasourceFunctions() {
    return array(
      'members' => array(
        'name' => pht('Members: ...'),
        'arguments' => pht('role'),
        'summary' => pht('Find results for members of a role.'),
        'description' => pht(
          'This function allows you to find results for any of the members '.
          'of a role:'.
          "\n\n".
          '> members(frontend)'),
      ),
    );
  }

  protected function didLoadResults(array $results) {
    foreach ($results as $result) {
      $result
        ->setTokenType(PhabricatorTypeaheadTokenView::TYPE_FUNCTION)
        ->setIcon('fa-users')
        ->setColor(null)
        ->setPHID('members('.$result->getPHID().')')
        ->setDisplayName(pht('Members: %s', $result->getDisplayName()))
        ->setName($result->getName().' members')
        ->resetAttributes()
        ->addAttribute(pht('Function'))
        ->addAttribute(pht('Select role members.'));
    }

    return $results;
  }

  protected function evaluateFunction($function, array $argv_list) {
    $phids = array();
    foreach ($argv_list as $argv) {
      $phids[] = head($argv);
    }

    $roles = id(new PhabricatorRoleQuery())
      ->setViewer($this->getViewer())
      ->needMembers(true)
      ->withPHIDs($phids)
      ->execute();

    $results = array();
    foreach ($roles as $role) {
      foreach ($role->getMemberPHIDs() as $phid) {
        $results[$phid] = $phid;
      }
    }

    return array_values($results);
  }

  public function renderFunctionTokens($function, array $argv_list) {
    $phids = array();
    foreach ($argv_list as $argv) {
      $phids[] = head($argv);
    }

    $tokens = $this->renderTokens($phids);
    foreach ($tokens as $token) {
      // Remove any role color on this token.
      $token->setColor(null);

      if ($token->isInvalid()) {
        $token
          ->setValue(pht('Members: Invalid Role'));
      } else {
        $token
          ->setIcon('fa-users')
          ->setTokenType(PhabricatorTypeaheadTokenView::TYPE_FUNCTION)
          ->setKey('members('.$token->getKey().')')
          ->setValue(pht('Members: %s', $token->getValue()));
      }
    }

    return $tokens;
  }

}
