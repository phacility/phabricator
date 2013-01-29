<?php

abstract class PhabricatorMetaMTAController extends PhabricatorController {

  public function shouldRequireAdmin() {
    return true;
  }

  public function buildSideNavView() {
    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI()));

    $nav->addLabel(pht('Mail Logs'));
    $nav->addFilter('sent', pht('Sent Mail'), $this->getApplicationURI());
    $nav->addFilter('received', pht('Received Mail'));

    if ($this->getRequest()->getUser()->getIsAdmin()) {
      $nav->addLabel(pht('Diagnostics'));
      $nav->addFilter('send', pht('Send Test'));
      $nav->addFilter('receive', pht('Receive Test'));
    }

    return $nav;
  }

}
