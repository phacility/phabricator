<?php

final class RoleColumnSearchConduitAPIMethod
  extends PhabricatorSearchEngineAPIMethod {

  public function getAPIMethodName() {
    return 'role.column.search';
  }

  public function newSearchEngine() {
    return new PhabricatorRoleColumnSearchEngine();
  }

  public function getMethodSummary() {
    return pht('Read information about workboard columns.');
  }

}
