<?php

/**
 * @group pholio
 */
abstract class PholioController extends PhabricatorController {

  public function buildSideNav() {
    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI()));

    $nav->addLabel('Create');
    $nav->addFilter('new', pht('Create Mock'));

    $nav->addLabel('Mocks');
    $nav->addFilter('view/all', pht('All Mocks'));

    return $nav;
  }


}
