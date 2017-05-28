<?php

final class ConpherenceEditConduitAPIMethod
  extends PhabricatorEditEngineAPIMethod {

  public function getAPIMethodName() {
    return 'conpherence.edit';
  }

  public function newEditEngine() {
    return new ConpherenceEditEngine();
  }

  public function getMethodSummary() {
    return pht(
      'Apply transactions to create a new room or edit an existing one.');
  }

}
