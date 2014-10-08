<?php

abstract class PhluxController extends PhabricatorController {

  protected function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();
    $crumbs->addAction(
      id(new PHUIListItemView())
        ->setName(pht('Create Variable'))
        ->setHref($this->getApplicationURI('/edit/'))
        ->setIcon('fa-plus-square'));

    return $crumbs;
  }

}
