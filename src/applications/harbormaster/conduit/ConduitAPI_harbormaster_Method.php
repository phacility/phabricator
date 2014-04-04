<?php

abstract class ConduitAPI_harbormaster_Method extends ConduitAPIMethod {

  public function getApplication() {
    return PhabricatorApplication::getByClass(
      'PhabricatorApplicationHarbormaster');
  }

  public function getMethodStatus() {
    return self::METHOD_STATUS_UNSTABLE;
  }

  public function getMethodStatusDescription() {
    return pht('All Harbormaster APIs are new and subject to change.');
  }

}
