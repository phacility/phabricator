<?php

abstract class PhabricatorDaemonController extends PhabricatorController {

  protected function buildSideNavView() {
    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI()));

    $nav->addLabel('Daemons');
    $nav->addFilter('/', 'Console');
    $nav->addFilter('log', 'All Daemons');
    $nav->addFilter('log/combined', 'Combined Log');

    return $nav;
  }

}
