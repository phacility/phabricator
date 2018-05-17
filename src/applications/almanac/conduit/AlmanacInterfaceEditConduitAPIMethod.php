<?php

final class AlmanacInterfaceEditConduitAPIMethod
  extends PhabricatorEditEngineAPIMethod {

  public function getAPIMethodName() {
    return 'almanac.interface.edit';
  }

  public function newEditEngine() {
    return new AlmanacInterfaceEditEngine();
  }

  public function getMethodSummary() {
    return pht(
      'Apply transactions to create a new interface or edit an existing one.');
  }

}
