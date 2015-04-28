<?php

final class ConduitPingConduitAPIMethod extends ConduitAPIMethod {

  public function getAPIMethodName() {
    return 'conduit.ping';
  }

  public function shouldRequireAuthentication() {
    return false;
  }

  public function getMethodDescription() {
    return 'Basic ping for monitoring or a health-check.';
  }

  protected function defineParamTypes() {
    return array();
  }

  protected function defineReturnType() {
    return 'string';
  }

  protected function execute(ConduitAPIRequest $request) {
    return php_uname('n');
  }

}
