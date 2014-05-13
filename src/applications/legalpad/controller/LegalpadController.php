<?php

/**
 * @group legalpad
 */
abstract class LegalpadController extends PhabricatorController {

  public function buildSideNav($for_app = false) {
    $user = $this->getRequest()->getUser();

    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI()));

    if ($for_app) {
      $nav->addFilter('create/', pht('Create Document'));
    }

    id(new LegalpadDocumentSearchEngine())
      ->setViewer($user)
      ->addNavigationItems($nav->getMenu());

    return $nav;
  }

  public function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    $crumbs->addAction(
      id(new PHUIListItemView())
        ->setName(pht('Create Document'))
        ->setHref($this->getApplicationURI('create/'))
        ->setIcon('fa-plus-square'));

    return $crumbs;
  }

  public function buildApplicationMenu() {
    return $this->buildSideNav(true)->getMenu();
  }

}
