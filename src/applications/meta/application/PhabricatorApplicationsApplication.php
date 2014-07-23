<?php

final class PhabricatorApplicationsApplication extends PhabricatorApplication {

  public function getName() {
    return pht('Applications');
  }

  public function canUninstall() {
    return false;
  }

  public function isLaunchable() {
    // This application is launchable in the traditional sense, but showing it
    // on the application launch list is confusing.
    return false;
  }

  public function getBaseURI() {
    return '/applications/';
  }

  public function getShortDescription() {
    return pht('Explore More Applications');
  }

  public function getIconName() {
    return 'application';
  }

  public function getTitleGlyph() {
    return "\xE0\xBC\x84";
  }

  public function getRoutes() {
    return array(
      '/applications/' => array(
        '(?:query/(?P<queryKey>[^/]+)/)?'
          => 'PhabricatorApplicationsListController',
        'view/(?P<application>\w+)/'
          => 'PhabricatorApplicationDetailViewController',
        'edit/(?P<application>\w+)/'
          => 'PhabricatorApplicationEditController',
        '(?P<application>\w+)/(?P<action>install|uninstall)/'
          => 'PhabricatorApplicationUninstallController',
      ),
    );
  }

}
