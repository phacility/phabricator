<?php

abstract class ConduitAPI_nuance_Method extends ConduitAPIMethod {

  public function getApplication() {
    return PhabricatorApplication::getByClass('PhabricatorNuanceApplication');
  }

  public function getMethodStatus() {
    return self::METHOD_STATUS_UNSTABLE;
  }

}
