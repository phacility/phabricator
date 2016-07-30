<?php

final class PhabricatorPackagesPackageEditConduitAPIMethod
  extends PhabricatorEditEngineAPIMethod {

  public function getAPIMethodName() {
    return 'packages.package.edit';
  }

  public function newEditEngine() {
    return new PhabricatorPackagesPackageEditEngine();
  }

  public function getMethodSummary() {
    return pht(
      'Apply transactions to create a new package or edit an existing one.');
  }

}
