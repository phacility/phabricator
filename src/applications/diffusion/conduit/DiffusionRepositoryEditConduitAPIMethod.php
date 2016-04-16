<?php

final class DiffusionRepositoryEditConduitAPIMethod
  extends PhabricatorEditEngineAPIMethod {

  public function getAPIMethodName() {
    return 'diffusion.repository.edit';
  }

  public function newEditEngine() {
    return new DiffusionRepositoryEditEngine();
  }

  public function getMethodSummary() {
    return pht(
      'Apply transactions to create a new repository or edit an existing '.
      'one.');
  }

}
