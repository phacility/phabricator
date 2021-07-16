<?php

final class HarbormasterBuildEditAPIMethod
  extends PhabricatorEditEngineAPIMethod {

  public function getAPIMethodName() {
    return 'harbormaster.build.edit';
  }

  public function newEditEngine() {
    return new HarbormasterBuildEditEngine();
  }

  public function getMethodSummary() {
    return pht(
      'Apply transactions to create a new build or edit an existing '.
      'one.');
  }

}
