<?php

final class PhabricatorDashboardApplication extends PhabricatorApplication {

  public function getName() {
    return pht('Dashboards');
  }

  public function getBaseURI() {
    return '/dashboard/';
  }

  public function getShortDescription() {
    return pht('Create Custom Pages');
  }

  public function getFontIcon() {
    return 'fa-dashboard';
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
        'copy/(?:(?P<id>\d+)/)?' => 'PhabricatorDashboardCopyController',
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
          'create/' => 'PhabricatorDashboardPanelEditController',
          'edit/(?:(?P<id>\d+)/)?' => 'PhabricatorDashboardPanelEditController',
          'render/(?P<id>\d+)/' => 'PhabricatorDashboardPanelRenderController',
          'archive/(?P<id>\d+)/'
            => 'PhabricatorDashboardPanelArchiveController',
        ),
      ),
    );
  }

  public function getRemarkupRules() {
    return array(
      new PhabricatorDashboardRemarkupRule(),
    );
  }

}
