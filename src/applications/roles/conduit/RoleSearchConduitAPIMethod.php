<?php

final class RoleSearchConduitAPIMethod
  extends PhabricatorSearchEngineAPIMethod {

  public function getAPIMethodName() {
    return 'role.search';
  }

  public function newSearchEngine() {
    return new PhabricatorRoleSearchEngine();
  }

  public function getMethodSummary() {
    return pht('Read information about roles.');
  }

  protected function getCustomQueryMaps($query) {
    return array(
      'slugMap' => $query->getSlugMap(),
    );
  }

}
