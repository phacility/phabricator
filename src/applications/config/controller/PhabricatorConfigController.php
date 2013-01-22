<?php

abstract class PhabricatorConfigController extends PhabricatorController {

  public function shouldRequireAdmin() {
    return true;
  }

  public function buildSideNavView($filter = null, $for_app = false) {
    $user = $this->getRequest()->getUser();

    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI()));
    $nav->addLabel(pht('Site Configuration'));
    $nav->addFilter('/', pht('Option Groups'));
    $nav->addFilter('issue/', pht('Setup Issues'));
    $nav->addFilter('all/', pht('Current Settings'));

    return $nav;
  }

  public function buildApplicationMenu() {
    return $this->buildSideNavView(null, true)->getMenu();
  }

}
