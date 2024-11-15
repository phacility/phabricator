<?php

final class PhabricatorRoleColumnSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('Workboard Columns');
  }

  public function getApplicationClassName() {
    return 'PhabricatorRoleApplication';
  }

  public function canUseInPanelContext() {
    return false;
  }

  public function newQuery() {
    return new PhabricatorRoleColumnQuery();
  }

  protected function buildCustomSearchFields() {
    return array(
      id(new PhabricatorPHIDsSearchField())
        ->setLabel(pht('Roles'))
        ->setKey('rolePHIDs')
        ->setConduitKey('roles')
        ->setAliases(array('role', 'roles', 'rolePHID')),
    );
  }

  protected function buildQueryFromParameters(array $map) {
    $query = $this->newQuery();

    if ($map['rolePHIDs']) {
      $query->withRolePHIDs($map['rolePHIDs']);
    }

    return $query;
  }

  protected function getURI($path) {
    // NOTE: There's no way to query columns in the web UI, at least for
    // the moment.
    return null;
  }

  protected function getBuiltinQueryNames() {
    $names = array();

    $names['all'] = pht('All');

    return $names;
  }

  public function buildSavedQueryFromBuiltin($query_key) {
    $query = $this->newSavedQuery();
    $query->setQueryKey($query_key);

    switch ($query_key) {
      case 'all':
        return $query;
    }

    return parent::buildSavedQueryFromBuiltin($query_key);
  }

  protected function renderResultList(
    array $roles,
    PhabricatorSavedQuery $query,
    array $handles) {
    assert_instances_of($roles, 'PhabricatorRoleColumn');
    $viewer = $this->requireViewer();

    return null;
  }

}
