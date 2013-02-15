<?php

abstract class PhabricatorTokenController extends PhabricatorController {


  protected function buildSideNav() {
    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI()));

    $nav->addFilter('given/', pht('Tokens Given'));

    return $nav;
  }


}
