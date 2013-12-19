<?php

abstract class HarbormasterPlanController extends PhabricatorController {

  public function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    $crumbs->addTextCrumb(
      pht('Build Plans'),
      $this->getApplicationURI('plan/'));

    return $crumbs;
  }

}
