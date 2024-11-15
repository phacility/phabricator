<?php

final class RoleEditConduitAPIMethod
  extends PhabricatorEditEngineAPIMethod {

  public function getAPIMethodName() {
    return 'role.edit';
  }

  public function newEditEngine() {
    return new PhabricatorRoleEditEngine();
  }

  public function getMethodSummary() {
    return pht(
      'Apply transactions to create a new role or edit an existing one.');
  }

}
