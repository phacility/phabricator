<?php

abstract class DrydockBlueprintController
  extends DrydockController {

  public function buildApplicationMenu() {
    return $this->newApplicationMenu()
      ->setSearchEngine(new DrydockBlueprintSearchEngine());
  }

  protected function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    $crumbs->addTextCrumb(
      pht('Blueprints'),
      $this->getApplicationURI('blueprint/'));

    return $crumbs;
  }

}
