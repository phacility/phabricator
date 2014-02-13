<?php

/**
 * @group conduit
 */
abstract class ConduitAPI_nuance_Method extends ConduitAPIMethod {

  public function getApplication() {
    return PhabricatorApplication::getByClass(
      'PhabricatorApplicationNuance');
  }

  public function getMethodStatus() {
    return self::METHOD_STATUS_UNSTABLE;
  }

}
