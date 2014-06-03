<?php

final class PhabricatorApplicationDashboard extends PhabricatorApplication {

  public function getBaseURI() {
    return '/dashboard/';
  }

  public function getShortDescription() {
    return pht('Create Custom Pages');
  }

  public function getIconName() {
    return 'fancyhome';
  }

  public function getRoutes() {
    return array(
      '/W(?P<id>\d+)' => 'PhabricatorDashboardPanelViewController',
      '/dashboard/' => array(
        '(?:query/(?P<queryKey>[^/]+)/)?'
          => 'PhabricatorDashboardListController',
        'view/(?P<id>\d+)/' => 'PhabricatorDashboardViewController',
        'manage/(?P<id>\d+)/' => 'PhabricatorDashboardManageController',
        'history/(?P<id>\d+)/' => 'PhabricatorDashboardHistoryController',
        'create/' => 'PhabricatorDashboardEditController',
        'edit/(?:(?P<id>\d+)/)?' => 'PhabricatorDashboardEditController',
        'install/(?P<id>\d+)/' => 'PhabricatorDashboardInstallController',
        'uninstall/(?P<id>\d+)/' => 'PhabricatorDashboardUninstallController',
        'addpanel/(?P<id>\d+)/' => 'PhabricatorDashboardAddPanelController',
        'movepanel/(?P<id>\d+)/' => 'PhabricatorDashboardMovePanelController',
        'removepanel/(?P<id>\d+)/'
          => 'PhabricatorDashboardRemovePanelController',
        'panel/' => array(
          '(?:query/(?P<queryKey>[^/]+)/)?'
            => 'PhabricatorDashboardPanelListController',
          'create/' => 'PhabricatorDashboardPanelCreateController',
          'edit/(?:(?P<id>\d+)/)?' => 'PhabricatorDashboardPanelEditController',
          'render/(?P<id>\d+)/' => 'PhabricatorDashboardPanelRenderController',
        ),
      ),
    );
  }

  public function getRemarkupRules() {
    return array(
      new PhabricatorDashboardRemarkupRule(),
    );
  }

  public function isBeta() {
    return true;
  }

  public function isLaunchable() {
    // TODO: This is just concealing the application from launch views for
    // now since it's not really beta yet.
    return false;
  }

  public function canUninstall() {
    return false;
  }

}
