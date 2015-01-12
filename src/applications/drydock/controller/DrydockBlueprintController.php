<?php

abstract class DrydockBlueprintController
  extends DrydockController {

  public function buildSideNavView() {
    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI()));

    id(new DrydockBlueprintSearchEngine())
      ->setViewer($this->getRequest()->getUser())
      ->addNavigationItems($nav->getMenu());

    $nav->selectFilter(null);

    return $nav;
  }

  protected function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();
    $crumbs->addTextCrumb(
      pht('Blueprints'),
      $this->getApplicationURI('blueprint/'));
    return $crumbs;
  }

}
