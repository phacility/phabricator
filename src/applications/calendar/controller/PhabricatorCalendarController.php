<?php

abstract class PhabricatorCalendarController extends PhabricatorController {

  protected function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    $crumbs->addAction(
      id(new PHUIListItemView())
        ->setName(pht('Create Event'))
        ->setHref($this->getApplicationURI().'event/create/')
        ->setIcon('fa-plus-square'));

    return $crumbs;
  }

}
