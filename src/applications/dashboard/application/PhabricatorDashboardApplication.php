<?php

final class PhabricatorDashboardApplication extends PhabricatorApplication {

  public function getName() {
    return pht('Dashboards');
  }

  public function getBaseURI() {
    return '/dashboard/';
  }

  public function getTypeaheadURI() {
    return '/dashboard/console/';
  }

  public function getShortDescription() {
    return pht('Create Custom Pages');
  }

  public function getIcon() {
    return 'fa-dashboard';
  }

  public function isPinnedByDefault(PhabricatorUser $viewer) {
    return true;
  }

  public function getApplicationOrder() {
    return 0.160;
  }

  public function getRoutes() {
    $menu_rules = $this->getProfileMenuRouting(
      'PhabricatorDashboardPortalViewController');

    return array(
      '/W(?P<id>\d+)' => 'PhabricatorDashboardPanelViewController',
      '/dashboard/' => array(
        '(?:query/(?P<queryKey>[^/]+)/)?'
          => 'PhabricatorDashboardListController',
        'view/(?P<id>\d+)/' => 'PhabricatorDashboardViewController',
        'archive/(?P<id>\d+)/' => 'PhabricatorDashboardArchiveController',
        'manage/(?P<id>\d+)/' => 'PhabricatorDashboardManageController',
        'arrange/(?P<id>\d+)/' => 'PhabricatorDashboardArrangeController',
        'create/' => 'PhabricatorDashboardEditController',
        'edit/(?:(?P<id>\d+)/)?' => 'PhabricatorDashboardEditController',
        'install/(?:(?P<id>\d+)/)?' => 'PhabricatorDashboardInstallController',
        'console/' => 'PhabricatorDashboardConsoleController',
        'addpanel/(?P<id>\d+)/' => 'PhabricatorDashboardAddPanelController',
        'movepanel/(?P<id>\d+)/' => 'PhabricatorDashboardMovePanelController',
        'removepanel/(?P<id>\d+)/'
          => 'PhabricatorDashboardRemovePanelController',
        'panel/' => array(
          'install/(?P<engineKey>[^/]+)/(?:(?P<queryKey>[^/]+)/)?' =>
            'PhabricatorDashboardQueryPanelInstallController',
          '(?:query/(?P<queryKey>[^/]+)/)?'
            => 'PhabricatorDashboardPanelListController',
          'create/' => 'PhabricatorDashboardPanelEditController',
          $this->getEditRoutePattern('editpro/')
            => 'PhabricatorDashboardPanelEditproController',
          'edit/(?:(?P<id>\d+)/)?' => 'PhabricatorDashboardPanelEditController',
          'render/(?P<id>\d+)/' => 'PhabricatorDashboardPanelRenderController',
          'archive/(?P<id>\d+)/'
            => 'PhabricatorDashboardPanelArchiveController',
        ),
      ),
      '/portal/' => array(
        $this->getQueryRoutePattern() =>
          'PhabricatorDashboardPortalListController',
        $this->getEditRoutePattern('edit/') =>
          'PhabricatorDashboardPortalEditController',
        'view/(?P<portalID>\d)/' => array(
            '' => 'PhabricatorDashboardPortalViewController',
          ) + $menu_rules,

      ),
    );
  }

  public function getRemarkupRules() {
    return array(
      new PhabricatorDashboardRemarkupRule(),
    );
  }

}
