<?php

abstract class HarbormasterController extends PhabricatorController {

  public function buildApplicationMenu() {
    return $this->newApplicationMenu()
      ->setSearchEngine(new HarbormasterBuildableSearchEngine());
  }

  protected function addBuildableCrumb(
    PHUICrumbsView $crumbs,
    HarbormasterBuildable $buildable) {

    $monogram = $buildable->getMonogram();
    $uri = '/'.$monogram;

    $crumbs->addTextCrumb($monogram, $uri);
  }

}
