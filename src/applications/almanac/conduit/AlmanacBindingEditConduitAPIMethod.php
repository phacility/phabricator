<?php

final class AlmanacBindingEditConduitAPIMethod
  extends PhabricatorEditEngineAPIMethod {

  public function getAPIMethodName() {
    return 'almanac.binding.edit';
  }

  public function newEditEngine() {
    return new AlmanacBindingEditEngine();
  }

  public function getMethodSummary() {
    return pht(
      'Apply transactions to create a new binding or edit an existing one.');
  }

}
