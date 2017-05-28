<?php

abstract class PhabricatorDaemonController
  extends PhabricatorController {

  public function shouldRequireAdmin() {
    return true;
  }

  public function buildApplicationMenu() {
    return $this->buildSideNavView(true)->getMenu();
  }

  protected function buildSideNavView() {
    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI()));

    $nav->addLabel(pht('Daemons'));
    $nav->addFilter('/', pht('Console'));
    $nav->addFilter('log', pht('All Daemons'));

    $nav->addLabel(pht('Bulk Jobs'));
    $nav->addFilter('bulk', pht('Manage Bulk Jobs'));

    return $nav;
  }

}
