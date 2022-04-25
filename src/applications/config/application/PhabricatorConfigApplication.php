<?php

final class PhabricatorConfigApplication extends PhabricatorApplication {

  public function getBaseURI() {
    return '/config/';
  }

  public function getIcon() {
    return 'fa-sliders';
  }

  public function isPinnedByDefault(PhabricatorUser $viewer) {
    return $viewer->getIsAdmin();
  }

  public function getTitleGlyph() {
    return "\xE2\x9C\xA8";
  }

  public function getApplicationGroup() {
    return self::GROUP_ADMIN;
  }

  public function canUninstall() {
    return false;
  }

  public function getName() {
    return pht('Config');
  }

  public function getShortDescription() {
    return pht('Configure %s', PlatformSymbols::getPlatformServerName());
  }

  public function getRoutes() {
    return array(
      '/config/' => array(
        '' => 'PhabricatorConfigConsoleController',
        'edit/(?P<key>[\w\.\-]+)/' => 'PhabricatorConfigEditController',
        'database/'.
          '(?:(?P<ref>[^/]+)/'.
          '(?:(?P<database>[^/]+)/'.
          '(?:(?P<table>[^/]+)/'.
          '(?:(?:col/(?P<column>[^/]+)|key/(?P<key>[^/]+))/)?)?)?)?'
          => 'PhabricatorConfigDatabaseStatusController',
        'dbissue/' => 'PhabricatorConfigDatabaseIssueController',
        '(?P<verb>ignore|unignore)/(?P<key>[^/]+)/'
          => 'PhabricatorConfigIgnoreController',
        'issue/' => array(
          '' => 'PhabricatorConfigIssueListController',
          'panel/' => 'PhabricatorConfigIssuePanelController',
          '(?P<key>[^/]+)/' => 'PhabricatorConfigIssueViewController',
        ),
        'cache/' => array(
          '' => 'PhabricatorConfigCacheController',
          'purge/' => 'PhabricatorConfigPurgeCacheController',
        ),
        'module/' => array(
          '(?:(?P<module>[^/]+)/)?' => 'PhabricatorConfigModuleController',
        ),
        'cluster/' => array(
          'databases/' => 'PhabricatorConfigClusterDatabasesController',
          'notifications/' => 'PhabricatorConfigClusterNotificationsController',
          'repositories/' => 'PhabricatorConfigClusterRepositoriesController',
          'search/' => 'PhabricatorConfigClusterSearchController',
        ),
        'settings/' => array(
          '' => 'PhabricatorConfigSettingsListController',
          '(?P<filter>advanced|all)/'
            => 'PhabricatorConfigSettingsListController',
          'history/' => 'PhabricatorConfigSettingsHistoryController',
        ),
      ),
    );
  }

}
