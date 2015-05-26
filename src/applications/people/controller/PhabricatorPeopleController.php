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
        $nav->addFilter("{$name}/feed/", pht('Feed'));
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

  public function buildIconNavView(PhabricatorUser $user) {
    $viewer = $this->getViewer();
    $picture = $user->getProfileImageURI();
    $name = $user->getUsername();

    $nav = new AphrontSideNavFilterView();
    $nav->setIconNav(true);
    $nav->setBaseURI(new PhutilURI('/p/'));
    $nav->addIcon("{$name}/", $name, null, $picture);
    $nav->addIcon("{$name}/feed/", pht('Feed'), 'fa-newspaper-o');

    $class = 'PhabricatorCalendarApplication';
    if (PhabricatorApplication::isClassInstalledForViewer($class, $viewer)) {
      $nav->addIcon(
        "{$name}/calendar/", pht('Calendar'), 'fa-calendar');
    }

    $class = 'PhabricatorManiphestApplication';
    if (PhabricatorApplication::isClassInstalledForViewer($class, $viewer)) {
      $phid = $user->getPHID();
      $view_uri = sprintf(
        '/maniphest/?statuses=open()&assigned=%s#R',
        $phid);
      $nav->addIcon(
        'maniphest', pht('Open Tasks'), 'fa-anchor', null, $view_uri);
    }

    $class = 'PhabricatorDifferentialApplication';
    if (PhabricatorApplication::isClassInstalledForViewer($class, $viewer)) {
      $username = phutil_escape_uri($name);
      $view_uri = '/differential/?authors='.$username;
      $nav->addIcon(
        'differential', pht('Revisions'), 'fa-cog', null, $view_uri);
    }

    $class = 'PhabricatorAuditApplication';
    if (PhabricatorApplication::isClassInstalledForViewer($class, $viewer)) {
      $username = phutil_escape_uri($name);
      $view_uri = '/audit/?authors='.$username;
      $nav->addIcon(
        'audit', pht('Commits'), 'fa-code', null, $view_uri);
    }

    return $nav;
  }

}
