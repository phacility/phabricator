<?php

abstract class PhabricatorCalendarController extends PhabricatorController {


  protected function buildSideNavView(PhabricatorCalendarEvent $status = null) {
    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI()));

    $nav->addLabel(pht('Calendar'));
    $nav->addFilter('/', pht('View All'));
    $nav->addFilter('event/create/', pht('New Status'));

    if ($status && $status->getID()) {
      $nav->addFilter('event/edit/'.$status->getID().'/', pht('Edit Status'));
    }
    $nav->addFilter('event/', pht('Upcoming Statuses'));

    return $nav;
  }

}
