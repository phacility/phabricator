<?php

abstract class PhabricatorOAuthClientController
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

  public function buildSideNavView($for_app = false) {
    $user = $this->getRequest()->getUser();

    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI()));

    id(new PhabricatorOAuthServerClientSearchEngine())
      ->setViewer($user)
      ->addNavigationItems($nav->getMenu());

    $nav->selectFilter(null);

    return $nav;
  }

}
