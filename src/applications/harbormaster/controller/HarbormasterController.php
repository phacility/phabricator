<?php

abstract class HarbormasterController extends PhabricatorController {

  public function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    $crumbs->addAction(
      id(new PHUIListItemView())
        ->setName(pht('New Build Plan'))
        ->setHref($this->getApplicationURI('plan/edit/'))
        ->setIcon('create'));

    return $crumbs;
  }

}
