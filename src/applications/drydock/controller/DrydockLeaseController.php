<?php

abstract class DrydockLeaseController
  extends DrydockController {

  public function buildSideNavView() {
    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI()));

    id(new DrydockLeaseSearchEngine())
      ->setViewer($this->getRequest()->getUser())
      ->addNavigationItems($nav->getMenu());

    $nav->selectFilter(null);

    return $nav;
  }

  protected function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();
    $crumbs->addTextCrumb(
      pht('Leases'),
      $this->getApplicationURI('lease/'));
    return $crumbs;
  }

}
