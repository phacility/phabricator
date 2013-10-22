<?php

abstract class HarbormasterPlanController extends PhabricatorController {

  public function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName(pht('Build Plans'))
        ->setHref($this->getApplicationURI('plan/')));

    return $crumbs;
  }

}
