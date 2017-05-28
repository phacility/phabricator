<?php

final class MacroEditConduitAPIMethod
  extends PhabricatorEditEngineAPIMethod {

  public function getAPIMethodName() {
    return 'macro.edit';
  }

  public function newEditEngine() {
    return new PhabricatorMacroEditEngine();
  }

  public function getMethodSummary() {
    return pht(
      'Apply transactions to create a new macro or edit an existing one.');
  }

}
