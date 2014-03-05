<?php

abstract class PhabricatorCalendarController extends PhabricatorController {


  protected function buildSideNavView(PhabricatorCalendarEvent $status = null) {
    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI()));

    $nav->addLabel(pht('Calendar'));
    $nav->addFilter('/', pht('My Events'));
    $nav->addFilter('all/', pht('View All'));
    $nav->addFilter('event/create/', pht('Create Event'));

    if ($status && $status->getID()) {
      $nav->addFilter('event/edit/'.$status->getID().'/', pht('Edit Event'));
    }
    $nav->addFilter('event/', pht('Upcoming Events'));

    return $nav;
  }

  public function buildApplicationMenu() {
    return $this->buildSideNavView()->getMenu();
  }

  public function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    $crumbs->addAction(
      id(new PHUIListItemView())
        ->setName(pht('Create Event'))
        ->setHref($this->getApplicationURI().'event/create')
        ->setIcon('create'));

    return $crumbs;
  }

}
