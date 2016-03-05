<?php

abstract class HarbormasterPlanController extends HarbormasterController {

  public function buildApplicationMenu() {
    return $this->newApplicationMenu()
      ->setSearchEngine(new HarbormasterBuildPlanSearchEngine());
  }

  protected function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    $crumbs->addTextCrumb(
      pht('Build Plans'),
      $this->getApplicationURI('plan/'));

    return $crumbs;
  }

}
