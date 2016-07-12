<?php

final class DiffusionURIEditConduitAPIMethod
  extends PhabricatorEditEngineAPIMethod {

  public function getAPIMethodName() {
    return 'diffusion.uri.edit';
  }

  public function newEditEngine() {
    return new DiffusionURIEditEngine();
  }

  public function getMethodSummary() {
    return pht(
      'Apply transactions to create a new repository URI or edit an existing '.
      'one.');
  }

}
