<?php

abstract class HarbormasterPlanController extends HarbormasterController {

  public function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    $crumbs->addTextCrumb(
      pht('Build Plans'),
      $this->getApplicationURI('plan/'));

    return $crumbs;
  }

}
