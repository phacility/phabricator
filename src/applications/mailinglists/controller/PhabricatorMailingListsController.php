<?php

abstract class PhabricatorMailingListsController extends PhabricatorController {

  public function buildSideNavView($filter = null, $for_app = false) {
    $user = $this->getRequest()->getUser();

    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI()));

    $nav->addLabel(pht('Mailing Lists'));
    $nav->addFilter('/', pht('All Lists'));
    $nav->selectFilter($filter, '/');
    if ($for_app) {
      $nav->addFilter('edit/', pht('Create List'));
    }

    return $nav;
  }

  public function buildApplicationMenu() {
    return $this->buildSideNavView(null, true)->getMenu();
  }

  public function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    $crumbs->addAction(
      id(new PhabricatorMenuItemView())
        ->setName(pht('Create List'))
        ->setHref($this->getApplicationURI('edit/'))
        ->setIcon('create'));

    return $crumbs;
  }

}
