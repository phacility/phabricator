<?php

abstract class PhabricatorDaemonController extends PhabricatorController {

  protected function buildSideNavView() {
    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI()));

    $nav->addLabel('Daemons');
    $nav->addFilter('', 'Console', $this->getApplicationURI());
    $nav->addFilter('log/running', 'Running Daemons');
    $nav->addFilter('log', 'All Daemons');
    $nav->addFilter('log/combined', 'Combined Log');

    $nav->addSpacer();
    $nav->addLabel('Event Timeline');
    $nav->addFilter('timeline', 'Timeline');

    return $nav;
  }

}
