<?php

abstract class PhabricatorCalendarController extends PhabricatorController {


  protected function buildSideNavView(PhabricatorUserStatus $status = null) {
    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI()));

    $nav->addLabel(pht('Calendar'));
    $nav->addFilter('/', pht('View All'));
    $nav->addFilter('status/create/', pht('New Status'));

    if ($status && $status->getID()) {
      $nav->addFilter('status/edit/'.$status->getID().'/', pht('Edit Status'));
    }
    $nav->addFilter('status/', pht('Upcoming Statuses'));

    return $nav;
  }

}
