<?php

abstract class PhabricatorMetaMTAController extends PhabricatorController {

  public function shouldRequireAdmin() {
    return true;
  }

  public function buildSideNavView() {
    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI()));

    $nav->addLabel('Mail Logs');
    $nav->addFilter('sent', 'Sent Mail', $this->getApplicationURI());
    $nav->addFilter('received', 'Received Mail');

    $nav->addSpacer();

    if ($this->getRequest()->getUser()->getIsAdmin()) {
      $nav->addLabel('Diagnostics');
      $nav->addFilter('send', 'Send Test');
      $nav->addFilter('receive', 'Receive Test');
    }

    return $nav;
  }

}
