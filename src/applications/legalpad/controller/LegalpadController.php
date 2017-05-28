<?php

abstract class LegalpadController extends PhabricatorController {

  public function buildSideNav($for_app = false) {
    $user = $this->getRequest()->getUser();

    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI()));

    if ($for_app) {
      $nav->addFilter('edit/', pht('Create Document'));
    }

    id(new LegalpadDocumentSearchEngine())
      ->setViewer($user)
      ->addNavigationItems($nav->getMenu());

    $nav->addLabel(pht('Signatures'));
    $nav->addFilter('signatures/', pht('Find Signatures'));

    return $nav;
  }

  public function buildApplicationMenu() {
    return $this->buildSideNav(true)->getMenu();
  }

}
