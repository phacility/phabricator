<?php

final class PhabricatorRedirectController extends PhabricatorController {

  private $uri;

  public function shouldRequireLogin() {
    return false;
  }

  public function shouldRequireEnabledUser() {
    return false;
  }

  public function willProcessRequest(array $data) {
    $this->uri = $data['uri'];
  }

  public function processRequest() {
    return id(new AphrontRedirectResponse())->setURI($this->uri);
  }

}
