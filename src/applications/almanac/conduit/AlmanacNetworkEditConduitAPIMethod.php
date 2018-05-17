<?php

final class AlmanacNetworkEditConduitAPIMethod
  extends PhabricatorEditEngineAPIMethod {

  public function getAPIMethodName() {
    return 'almanac.network.edit';
  }

  public function newEditEngine() {
    return new AlmanacNetworkEditEngine();
  }

  public function getMethodSummary() {
    return pht(
      'Apply transactions to create a new network or edit an existing one.');
  }

}
