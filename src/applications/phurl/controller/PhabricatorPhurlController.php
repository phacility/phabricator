<?php

abstract class PhabricatorPhurlController extends PhabricatorController {

  protected function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();
    $crumbs->addAction(
      id(new PHUIListItemView())
        ->setName(pht('Shorten URL'))
        ->setHref($this->getApplicationURI().'url/create/')
        ->setIcon('fa-plus-square'));

    return $crumbs;
  }
}
