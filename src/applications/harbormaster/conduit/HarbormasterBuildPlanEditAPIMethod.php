<?php

final class HarbormasterBuildPlanEditAPIMethod
  extends PhabricatorEditEngineAPIMethod {

  public function getAPIMethodName() {
    return 'harbormaster.buildplan.edit';
  }

  public function newEditEngine() {
    return new HarbormasterBuildPlanEditEngine();
  }

  public function getMethodSummary() {
    return pht(
      'Apply transactions to create a new build plan or edit an existing '.
      'one.');
  }

}
