<?php

final class OwnersEditConduitAPIMethod
  extends PhabricatorEditEngineAPIMethod {

  public function getAPIMethodName() {
    return 'owners.edit';
  }

  public function newEditEngine() {
    return new PhabricatorOwnersPackageEditEngine();
  }

  public function getMethodSummary() {
    return pht(
      'Apply transactions to create a new Owners package or edit an existing '.
      'one.');
  }

}
