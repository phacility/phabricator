<?php

abstract class DrydockLogController
  extends DrydockController {

  public function buildSideNavView() {
    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI()));

    id(new DrydockLogSearchEngine())
      ->setViewer($this->getRequest()->getUser())
      ->addNavigationItems($nav->getMenu());

    $nav->selectFilter(null);

    return $nav;
  }

  protected function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();
    $crumbs->addTextCrumb(
      pht('Logs'),
      $this->getApplicationURI('log/'));
    return $crumbs;
  }

}
