<?php

final class PasteEditConduitAPIMethod
  extends PhabricatorEditEngineAPIMethod {

  public function getAPIMethodName() {
    return 'paste.edit';
  }

  public function newEditEngine() {
    return new PhabricatorPasteEditEngine();
  }

  public function getMethodSummary() {
    return pht(
      'Apply transactions to create a new paste or edit an existing one.');
  }

}
