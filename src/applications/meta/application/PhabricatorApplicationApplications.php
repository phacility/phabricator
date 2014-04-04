<?php

final class PhabricatorApplicationApplications extends PhabricatorApplication {

  public function canUninstall() {
    return false;
  }

  public function getBaseURI() {
    return '/applications/';
  }

  public function getShortDescription() {
    return 'Installed Applications';
  }

  public function getIconName() {
    return 'application';
  }

  public function getTitleGlyph() {
    return "\xE0\xBC\x84";
  }

  public function getApplicationGroup() {
    return self::GROUP_ADMIN;
  }

 public function getRoutes() {
    return array(
      '/applications/' => array(
        '(?:query/(?P<queryKey>[^/]+)/)?' =>
          'PhabricatorApplicationsListController',
        'view/(?P<application>\w+)/' =>
          'PhabricatorApplicationDetailViewController',
        'edit/(?P<application>\w+)/' =>
          'PhabricatorApplicationEditController',
        '(?P<application>\w+)/(?P<action>install|uninstall)/' =>
          'PhabricatorApplicationUninstallController',
        ),

    );
  }

}
