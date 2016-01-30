<?php

final class PhabricatorSettingsApplication extends PhabricatorApplication {

  public function getBaseURI() {
    return '/settings/';
  }

  public function getName() {
    return pht('Settings');
  }

  public function getShortDescription() {
    return pht('User Preferences');
  }

  public function getIcon() {
    return 'fa-wrench';
  }

  public function canUninstall() {
    return false;
  }

  public function isLaunchable() {
    return false;
  }

  public function getRoutes() {
    return array(
      '/settings/' => array(
        '(?:(?P<id>\d+)/)?(?:panel/(?P<key>[^/]+)/)?'
          => 'PhabricatorSettingsMainController',
        'adjust/' => 'PhabricatorSettingsAdjustController',
      ),
    );
  }

  public function getApplicationGroup() {
    return self::GROUP_UTILITIES;
  }

}
