<?php

abstract class PhabricatorGuideController extends PhabricatorController {

  public function buildSideNavView($filter = null, $for_app = false) {

    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI()));
    $nav->addLabel(pht('Guides'));
    $nav->addFilter('/', pht('Welcome'));
    $nav->addFilter('install/', pht('Installation Guide'));
    $nav->addFilter('quickstart/', pht('Quick Start Guide'));

    return $nav;
  }

  public function buildApplicationMenu() {
    return $this->buildSideNavView(null, true)->getMenu();
  }

}
