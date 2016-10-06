<?php

final class PhabricatorPhurlURLEditConduitAPIMethod
  extends PhabricatorEditEngineAPIMethod {

  public function getAPIMethodName() {
    return 'phurls.edit';
  }

  public function newEditEngine() {
    return new PhabricatorPhurlURLEditEngine();
  }

  public function getMethodSummary() {
    return pht(
      'Apply transactions to create a new Phurl URL or edit an existing one.');
  }
}
