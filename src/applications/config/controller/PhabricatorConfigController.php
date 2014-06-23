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
    $nav->addFilter('all/', pht('All Settings'));
    $nav->addFilter('issue/', pht('Setup Issues'));
    $nav->addFilter('welcome/', pht('Welcome Screen'));

    return $nav;
  }

  public function buildApplicationMenu() {
    return $this->buildSideNavView(null, true)->getMenu();
  }

}
