<?php

abstract class PhabricatorCalendarController extends PhabricatorController {


  protected function buildSideNavView(PhabricatorUserStatus $status = null) {
    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI()));

    $nav->addFilter('', pht('Calendar'), $this->getApplicationURI());

    $nav->addSpacer();

    $nav->addLabel(pht('Create Events'));
    $nav->addFilter('status/create/', pht('New Status'));

    $nav->addSpacer();
    $nav->addLabel(pht('Your Events'));
    if ($status && $status->getID()) {
      $nav->addFilter('status/edit/'.$status->getID().'/', pht('Edit Status'));
    }
    $nav->addFilter('status/', pht('Upcoming Statuses'));

    return $nav;
  }

}
