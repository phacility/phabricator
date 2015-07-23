<?php

abstract class HarbormasterController extends PhabricatorController {

  protected function addBuildableCrumb(
    PHUICrumbsView $crumbs,
    HarbormasterBuildable $buildable) {

    $monogram = $buildable->getMonogram();
    $uri = '/'.$monogram;

    $crumbs->addTextCrumb($monogram, $uri);
  }

}
