<?php

abstract class ConduitAPI_releeph_Method extends ConduitAPIMethod {

  public function getMethodStatus() {
    return self::METHOD_STATUS_UNSTABLE;
  }

  public function getMethodStatusDescription() {
    return pht('All Releeph methods are subject to abrupt change.');
  }

  public function getApplication() {
    return PhabricatorApplication::getByClass('PhabricatorApplicationReleeph');
  }

}
