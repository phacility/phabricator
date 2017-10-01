<?php

final class AlamancServiceEditConduitAPIMethod
  extends PhabricatorEditEngineAPIMethod {

  public function getAPIMethodName() {
    return 'almanac.service.edit';
  }

  public function newEditEngine() {
    return new AlmanacServiceEditEngine();
  }

  public function getMethodSummary() {
    return pht(
      'Apply transactions to create a new service or edit an existing one.');
  }

}
