<?php

final class ManiphestEditConduitAPIMethod
  extends PhabricatorEditEngineAPIMethod {

  public function getAPIMethodName() {
    return 'maniphest.edit';
  }

  public function newEditEngine() {
    return new ManiphestEditEngine();
  }

  public function getMethodSummary() {
    return pht(
      'Apply transactions to create a new task or edit an existing one.');
  }

}
