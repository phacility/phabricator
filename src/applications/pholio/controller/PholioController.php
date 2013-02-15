<?php

/**
 * @group pholio
 */
abstract class PholioController extends PhabricatorController {

  public function buildSideNav($filter = null, $for_app = false) {
    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI()));

    $nav->addLabel('Mocks');
    $nav->addFilter('view/all', pht('All Mocks'));
    $nav->addFilter('view/my', pht('My Mocks'));

    if ($for_app) {
      $nav->addFilter('new/', pht('Create Mock'));
    }

    return $nav;
  }

  public function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    $crumbs->addAction(
      id(new PhabricatorMenuItemView())
        ->setName(pht('Create Mock'))
        ->setHref($this->getApplicationURI('new/'))
        ->setIcon('create'));

    return $crumbs;
  }

  public function buildApplicationMenu() {
    return $this->buildSideNav(null, true)->getMenu();
  }



}
