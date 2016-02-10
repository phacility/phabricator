<?php

abstract class PhabricatorPhurlController extends PhabricatorController {

  protected function buildApplicationCrumbs() {
    $can_create = $this->hasApplicationCapability(
      PhabricatorPhurlURLCreateCapability::CAPABILITY);

    $crumbs = parent::buildApplicationCrumbs();
    $crumbs->addAction(
      id(new PHUIListItemView())
        ->setName(pht('Shorten URL'))
        ->setHref($this->getApplicationURI().'url/create/')
        ->setIcon('fa-plus-square')
        ->setDisabled(!$can_create)
        ->setWorkflow(!$can_create));

    return $crumbs;
  }
}
