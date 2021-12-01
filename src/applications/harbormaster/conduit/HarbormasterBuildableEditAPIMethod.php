<?php

final class HarbormasterBuildableEditAPIMethod
  extends PhabricatorEditEngineAPIMethod {

  public function getAPIMethodName() {
    return 'harbormaster.buildable.edit';
  }

  public function newEditEngine() {
    return new HarbormasterBuildableEditEngine();
  }

  public function getMethodSummary() {
    return pht(
      'Apply transactions to create a new buildable or edit an existing '.
      'one.');
  }

}
