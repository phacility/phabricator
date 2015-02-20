<?php

abstract class PholioController extends PhabricatorController {

  public function buildSideNavView($for_app = false) {
    $user = $this->getRequest()->getUser();

    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI()));

    id(new PholioMockSearchEngine())
      ->setViewer($user)
      ->addNavigationItems($nav->getMenu());

    if ($for_app) {
      $nav->addFilter('new/', pht('Create Mock'));
    }

    $nav->selectFilter(null);

    return $nav;
  }

  protected function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    $crumbs->addAction(
      id(new PHUIListItemView())
        ->setName(pht('Create Mock'))
        ->setHref($this->getApplicationURI('new/'))
        ->setIcon('fa-plus-square'));

    return $crumbs;
  }

  public function buildApplicationMenu() {
    return $this->buildSideNavView(true)->getMenu();
  }

}
