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
    return "\xE2\x98\xBA";
  }

  public function getApplicationGroup() {
    return self::GROUP_ADMIN;
  }

  public function canUninstall() {
    return false;
  }

  public function getName() {
    return 'Config';
  }

  public function getShortDescription() {
    return pht('Configure Phabricator');
  }

  public function getRoutes() {
    return array(
      '/config/' => array(
        '' => 'PhabricatorConfigListController',
        'application/' => 'PhabricatorConfigApplicationController',
        'all/' => 'PhabricatorConfigAllController',
        'history/' => 'PhabricatorConfigHistoryController',
        'edit/(?P<key>[\w\.\-]+)/' => 'PhabricatorConfigEditController',
        'group/(?P<key>[^/]+)/' => 'PhabricatorConfigGroupController',
        'version/' => 'PhabricatorConfigVersionController',
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
          '(?P<module>[^/]+)/' => 'PhabricatorConfigModuleController',
        ),
        'cluster/' => array(
          'databases/' => 'PhabricatorConfigClusterDatabasesController',
          'notifications/' => 'PhabricatorConfigClusterNotificationsController',
          'repositories/' => 'PhabricatorConfigClusterRepositoriesController',
        ),
      ),
    );
  }

}
