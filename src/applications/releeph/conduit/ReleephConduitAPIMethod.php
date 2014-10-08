<?php

abstract class ReleephConduitAPIMethod extends ConduitAPIMethod {

  public function getMethodStatus() {
    return self::METHOD_STATUS_UNSTABLE;
  }

  public function getMethodStatusDescription() {
    return pht('All Releeph methods are subject to abrupt change.');
  }

  final public function getApplication() {
    return PhabricatorApplication::getByClass('PhabricatorReleephApplication');
  }

}
