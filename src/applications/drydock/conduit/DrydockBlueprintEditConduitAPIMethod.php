<?php

final class DrydockBlueprintEditConduitAPIMethod
  extends PhabricatorEditEngineAPIMethod {

  public function getAPIMethodName() {
    return 'drydock.blueprint.edit';
  }

  public function newEditEngine() {
    return new DrydockBlueprintEditEngine();
  }

  public function getMethodSummary() {
    return pht(
      'WARNING: Apply transactions to edit an existing blueprint. This method '.
      'can not create new blueprints.');
  }

}
