<?php

final class PhabricatorBadgesEditConduitAPIMethod
  extends PhabricatorEditEngineAPIMethod {

  public function getAPIMethodName() {
    return 'badge.edit';
  }

  public function newEditEngine() {
    return new PhabricatorBadgesEditEngine();
  }

  public function getMethodSummary() {
    return pht(
      'Apply transactions to create a new badge or edit an existing one.');
  }

}
