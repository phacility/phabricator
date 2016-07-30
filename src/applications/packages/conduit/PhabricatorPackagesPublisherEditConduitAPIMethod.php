<?php

final class PhabricatorPackagesPublisherEditConduitAPIMethod
  extends PhabricatorEditEngineAPIMethod {

  public function getAPIMethodName() {
    return 'packages.publisher.edit';
  }

  public function newEditEngine() {
    return new PhabricatorPackagesPublisherEditEngine();
  }

  public function getMethodSummary() {
    return pht(
      'Apply transactions to create a new publisher or edit an existing one.');
  }

}
