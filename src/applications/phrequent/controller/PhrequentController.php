<?php

abstract class PhrequentController extends PhabricatorController {

  protected function buildNav($view) {
    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI('/phrequent/view/'));

    $nav->addLabel(pht('User Times'));
    $nav->addFilter('current', pht('Currently Tracking'));
    $nav->addFilter('recent', pht('Recent Activity'));
    $nav->addLabel('All Times');
    $nav->addFilter('allcurrent', pht('Currently Tracking'));
    $nav->addFilter('allrecent', pht('Recent Activity'));

    $nav->selectFilter($view);

    return $nav;
  }
}
