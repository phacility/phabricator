<?php

/**
 * @group oauthserver
 */
abstract class PhabricatorOAuthClientBaseController
extends PhabricatorOAuthServerController {

  private $clientPHID;
  protected function getClientPHID() {
    return $this->clientPHID;
  }
  private function setClientPHID($phid) {
    $this->clientPHID = $phid;
    return $this;
  }

  public function shouldRequireLogin() {
    return true;
  }

  public function willProcessRequest(array $data) {
    $this->setClientPHID(idx($data, 'phid'));
  }
}
