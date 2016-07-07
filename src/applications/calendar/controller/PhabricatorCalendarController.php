<?php

abstract class PhabricatorCalendarController extends PhabricatorController {

  protected function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    $actions = id(new PhabricatorActionListView())
      ->setUser($this->getViewer())
      ->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Create Event'))
          ->setHref('/calendar/event/create/'))
      ->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Create Public Event'))
          ->setHref('/calendar/event/create/?mode=public'))
      ->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Create Recurring Event'))
          ->setHref('/calendar/event/create/?mode=recurring'));

    $crumbs->addAction(
      id(new PHUIListItemView())
        ->setName(pht('Create Event'))
        ->setHref($this->getApplicationURI().'event/create/')
        ->setIcon('fa-plus-square')
        ->setDropdownMenu($actions));

    return $crumbs;
  }

}
