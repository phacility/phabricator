<?php

abstract class PhabricatorAuthConduitAPIMethod extends ConduitAPIMethod {

  final public function getApplication() {
    return PhabricatorApplication::getByClass('PhabricatorAuthApplication');
  }

  public function getMethodStatus() {
    return self::METHOD_STATUS_UNSTABLE;
  }

  public function getMethodStatusDescription() {
    return pht('These methods are recently introduced and subject to change.');
  }

}
