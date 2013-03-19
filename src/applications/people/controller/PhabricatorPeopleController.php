<?php

abstract class PhabricatorPeopleController extends PhabricatorController {

  public function shouldRequireAdmin() {
    return true;
  }

  public function buildSideNavView() {
    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI()));

    $nav->addLabel(pht('User Administration'));
    $nav->addFilter('edit', pht('Create New User'));
    if (PhabricatorEnv::getEnvConfig('ldap.auth-enabled') === true) {
      $nav->addFilter('ldap', pht('Import from LDAP'));
    }

    $nav->addFilter('people',
      pht('User Directory'),
      $this->getApplicationURI());

    $nav->addFilter('logs', pht('Activity Logs'));

    return $nav;
  }

  public function buildApplicationMenu() {
    return $this->buildSideNavView()->getMenu();
  }

  public function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    $crumbs->addAction(
      id(new PhabricatorMenuItemView())
        ->setName(pht('Create New User'))
        ->setHref($this->getApplicationURI('edit'))
        ->setIcon('create'));

    return $crumbs;
  }

}
