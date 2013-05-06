<?php

abstract class PhabricatorCountdownController extends PhabricatorController {

  public function buildSideNavView() {
    $user = $this->getRequest()->getUser();

    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI()));

    $nav->addFilter('', pht('All Countdowns'),
      $this->getApplicationURI(''));
    $nav->addFilter('', pht('Create Countdown'),
      $this->getApplicationURI('edit/'));

    return $nav;
  }

  public function buildApplicationMenu() {
    return $this->buildSideNavView()->getMenu();
  }

  public function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    $crumbs->addAction(
      id(new PhabricatorMenuItemView())
        ->setName(pht('Create Countdown'))
        ->setHref($this->getApplicationURI('edit/'))
        ->setIcon('create'));

    return $crumbs;
  }
}
