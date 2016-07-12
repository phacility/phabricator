<?php

abstract class PholioController extends PhabricatorController {

  public function buildApplicationMenu() {
    return $this->newApplicationMenu()
      ->setSearchEngine(new PholioMockSearchEngine());
  }

  protected function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    $crumbs->addAction(
      id(new PHUIListItemView())
        ->setName(pht('Create Mock'))
        ->setHref($this->getApplicationURI('create/'))
        ->setIcon('fa-plus-square'));

    return $crumbs;
  }

}
