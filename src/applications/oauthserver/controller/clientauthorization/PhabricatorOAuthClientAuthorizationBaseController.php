<?php

/**
 * @group oauthserver
 */
abstract class PhabricatorOAuthClientAuthorizationBaseController
extends PhabricatorOAuthServerController {

  private $authorizationPHID;
  protected function getAuthorizationPHID() {
    return $this->authorizationPHID;
  }
  private function setAuthorizationPHID($phid) {
    $this->authorizationPHID = $phid;
    return $this;
  }

  public function shouldRequireLogin() {
    return true;
  }

  public function willProcessRequest(array $data) {
    $this->setAuthorizationPHID(idx($data, 'phid'));
  }
}
