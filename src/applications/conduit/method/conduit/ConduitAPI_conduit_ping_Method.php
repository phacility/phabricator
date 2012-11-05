<?php

/**
 * @group conduit
 */
final class ConduitAPI_conduit_ping_Method extends ConduitAPIMethod {

  public function shouldRequireAuthentication() {
    return false;
  }

  public function getMethodDescription() {
    return "Basic ping for monitoring or a health-check.";
  }

  public function defineParamTypes() {
    return array();
  }

  public function defineReturnType() {
    return 'string';
  }

  public function defineErrorTypes() {
    return array();
  }

  protected function execute(ConduitAPIRequest $request) {
    return php_uname('n');
  }
}
