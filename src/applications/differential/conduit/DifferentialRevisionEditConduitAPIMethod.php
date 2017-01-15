<?php

final class DifferentialRevisionEditConduitAPIMethod
  extends PhabricatorEditEngineAPIMethod {

  public function getAPIMethodName() {
    return 'differential.revision.edit';
  }

  public function newEditEngine() {
    return new DifferentialRevisionEditEngine();
  }

  public function getMethodSummary() {
    return pht('Apply transactions to create or update a revision.');
  }

}
