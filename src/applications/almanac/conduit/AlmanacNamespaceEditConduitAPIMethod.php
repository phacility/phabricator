<?php

final class AlmanacNamespaceEditConduitAPIMethod
  extends PhabricatorEditEngineAPIMethod {

  public function getAPIMethodName() {
    return 'almanac.namespace.edit';
  }

  public function newEditEngine() {
    return new AlmanacNamespaceEditEngine();
  }

  public function getMethodSummary() {
    return pht(
      'Apply transactions to create a new namespace or edit an existing one.');
  }

}
