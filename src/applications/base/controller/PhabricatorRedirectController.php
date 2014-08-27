<?php

final class PhabricatorRedirectController extends PhabricatorController {

  private $uri;
  private $allowExternal;

  public function shouldRequireLogin() {
    return false;
  }

  public function shouldRequireEnabledUser() {
    return false;
  }

  public function willProcessRequest(array $data) {
    $this->uri = $data['uri'];
    $this->allowExternal = idx($data, 'external', false);
  }

  public function processRequest() {
    return id(new AphrontRedirectResponse())
      ->setURI($this->uri)
      ->setIsExternal($this->allowExternal);
  }

}
