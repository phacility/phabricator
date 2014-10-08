<?php

final class PhabricatorTestController extends PhabricatorController {

  private $config = array();

  public function setConfig($key, $value) {
    $this->config[$key] = $value;
    return $this;
  }

  public function getConfig($key, $default) {
    return idx($this->config, $key, $default);
  }

  public function shouldRequireLogin() {
    return $this->getConfig('login', parent::shouldRequireLogin());
  }

  public function shouldRequireAdmin() {
    return $this->getConfig('admin', parent::shouldRequireAdmin());
  }

  public function shouldAllowPublic() {
    return $this->getConfig('public', parent::shouldAllowPublic());
  }

  public function shouldRequireEmailVerification() {
    return $this->getConfig('email', parent::shouldRequireEmailVerification());
  }

  public function shouldRequireEnabledUser() {
    return $this->getConfig('enabled', parent::shouldRequireEnabledUser());
  }

  public function processRequest() {}

}
