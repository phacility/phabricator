<?php

abstract class DrydockResourceController
  extends DrydockController {

  public function buildSideNavView() {
    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI()));

    id(new DrydockResourceSearchEngine())
      ->setViewer($this->getRequest()->getUser())
      ->addNavigationItems($nav->getMenu());

    $nav->selectFilter(null);

    return $nav;
  }

  protected function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();
    $crumbs->addTextCrumb(
      pht('Resources'),
      $this->getApplicationURI('resource/'));
    return $crumbs;
  }

}
