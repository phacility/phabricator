<?php

abstract class PhrequentController extends PhabricatorController {

  protected function buildNav($view) {
    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI('/phrequent/'));

    $nav->addFilter('usertime', 'Time Tracked');

    $nav->selectFilter($view);

    return $nav;
  }
}
