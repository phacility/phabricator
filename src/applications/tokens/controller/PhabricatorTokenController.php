<?php

abstract class PhabricatorTokenController extends PhabricatorController {

  protected function buildSideNav() {
    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI()));

    $nav->addLabel(pht('Tokens'));
    $nav->addFilter('given/', pht('Tokens Given'));
    $nav->addFilter('leaders/', pht('Leader Board'));

    return $nav;
  }

  public function buildApplicationMenu() {
    return $this->buildSideNav()->getMenu();
  }

  protected function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();
    return $crumbs;
  }

}
