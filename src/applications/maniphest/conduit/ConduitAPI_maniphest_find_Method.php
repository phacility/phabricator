<?php

/**
 * @group conduit
 *
 * @concrete-extensible
 */
final class ConduitAPI_maniphest_find_Method
  extends ConduitAPI_maniphest_query_Method {

  public function getMethodStatus() {
    return self::METHOD_STATUS_DEPRECATED;
  }

  public function getMethodStatusDescription() {
    return "Renamed to 'maniphest.query'.";
  }

  public function getMethodDescription() {
    return "Deprecated alias of maniphest.query";
  }

}
