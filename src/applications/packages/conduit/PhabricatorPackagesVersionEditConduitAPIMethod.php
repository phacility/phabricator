<?php

final class PhabricatorPackagesVersionEditConduitAPIMethod
  extends PhabricatorEditEngineAPIMethod {

  public function getAPIMethodName() {
    return 'packages.version.edit';
  }

  public function newEditEngine() {
    return new PhabricatorPackagesVersionEditEngine();
  }

  public function getMethodSummary() {
    return pht(
      'Apply transactions to create a new version or edit an existing one.');
  }

}
