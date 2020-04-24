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

  public function getRoutes() {
    $panel_pattern = '(?:page/(?P<pageKey>[^/]+)/)?';

    return array(
      '/settings/' => array(
        $this->getQueryRoutePattern() => 'PhabricatorSettingsListController',
        'user/(?P<username>[^/]+)/'.$panel_pattern
          => 'PhabricatorSettingsMainController',
        'builtin/(?P<builtin>global)/'.$panel_pattern
          => 'PhabricatorSettingsMainController',
        'panel/(?P<panel>[^/]+)/'
          => 'PhabricatorSettingsMainController',
        'adjust/' => 'PhabricatorSettingsAdjustController',
        'timezone/(?P<offset>[^/]+)/'
          => 'PhabricatorSettingsTimezoneController',
        'issue/' => 'PhabricatorSettingsIssueController',
      ),
    );
  }

  public function getApplicationGroup() {
    return self::GROUP_UTILITIES;
  }

}
