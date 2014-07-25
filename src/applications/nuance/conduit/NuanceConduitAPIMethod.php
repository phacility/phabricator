<?php

abstract class NuanceConduitAPIMethod extends ConduitAPIMethod {

  final public function getApplication() {
    return PhabricatorApplication::getByClass('PhabricatorApplicationNuance');
  }

  public function getMethodStatus() {
    return self::METHOD_STATUS_UNSTABLE;
  }

}
