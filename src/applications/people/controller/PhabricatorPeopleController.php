<?php

abstract class PhabricatorPeopleController extends PhabricatorController {

  public function shouldRequireAdmin() {
    return true;
  }

  public function buildSideNavView($for_app = false) {
    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI()));

    $name = null;
    if ($for_app) {
      $name = $this->getRequest()->getURIData('username');
      if ($name) {
        $nav->setBaseURI(new PhutilURI('/p/'));
        $nav->addFilter("{$name}/", $name);
        $nav->addFilter("{$name}/calendar/", pht('Calendar'));
      }
    }

    if (!$name) {
      $viewer = $this->getRequest()->getUser();
      id(new PhabricatorPeopleSearchEngine())
        ->setViewer($viewer)
        ->addNavigationItems($nav->getMenu());

      if ($viewer->getIsAdmin()) {
        $nav->addLabel(pht('User Administration'));
        if (PhabricatorLDAPAuthProvider::getLDAPProvider()) {
          $nav->addFilter('ldap', pht('Import from LDAP'));
        }

        $nav->addFilter('logs', pht('Activity Logs'));
        $nav->addFilter('invite', pht('Email Invitations'));
      }
    }

    return $nav;
  }

  public function buildApplicationMenu() {
    return $this->buildSideNavView(true)->getMenu();
  }

}
