<?php

/**
 * @concrete-extensible
 */
final class ManiphestFindConduitAPIMethod
  extends ManiphestQueryConduitAPIMethod {

  public function getAPIMethodName() {
    return 'maniphest.find';
  }

  public function getMethodStatus() {
    return self::METHOD_STATUS_DEPRECATED;
  }

  public function getMethodStatusDescription() {
    return "Renamed to 'maniphest.query'.";
  }

  public function getMethodDescription() {
    return 'Deprecated alias of maniphest.query';
  }

}
