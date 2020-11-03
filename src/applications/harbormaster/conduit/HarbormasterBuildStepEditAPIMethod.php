<?php

final class HarbormasterBuildStepEditAPIMethod
  extends PhabricatorEditEngineAPIMethod {

  public function getAPIMethodName() {
    return 'harbormaster.step.edit';
  }

  public function newEditEngine() {
    return new HarbormasterBuildStepEditEngine();
  }

  public function getMethodSummary() {
    return pht(
      'Apply transactions to create a new build step or edit an existing '.
      'one.');
  }

}
