<?php

final class CountdownEditConduitAPIMethod
  extends PhabricatorEditEngineAPIMethod {

  public function getAPIMethodName() {
    return 'countdown.edit';
  }

  public function newEditEngine() {
    return new PhabricatorCountdownEditEngine();
  }

  public function getMethodSummary() {
    return pht(
      'Apply transactions to create a new countdown or edit an existing one.');
  }
}
