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
        $this->getEditRoutePattern('edit/') =>
          'PhabricatorDashboardEditController',
        'install/(?P<id>\d+)/'.
          '(?:(?P<workflowKey>[^/]+)/'.
          '(?:(?P<modeKey>[^/]+)/)?)?' =>
          'PhabricatorDashboardInstallController',
        'console/' => 'PhabricatorDashboardConsoleController',
        'adjust/(?P<op>remove|add|move)/'
          => 'PhabricatorDashboardAdjustController',
        'panel/' => array(
          'install/(?P<engineKey>[^/]+)/(?:(?P<queryKey>[^/]+)/)?' =>
            'PhabricatorDashboardQueryPanelInstallController',
          '(?:query/(?P<queryKey>[^/]+)/)?'
            => 'PhabricatorDashboardPanelListController',
          $this->getEditRoutePattern('edit/')
            => 'PhabricatorDashboardPanelEditController',
          'render/(?P<id>\d+)/' => 'PhabricatorDashboardPanelRenderController',
          'archive/(?P<id>\d+)/'
            => 'PhabricatorDashboardPanelArchiveController',
          'tabs/(?P<id>\d+)/(?P<op>add|move|remove|rename)/'
            => 'PhabricatorDashboardPanelTabsController',
        ),
      ),
      '/portal/' => array(
        $this->getQueryRoutePattern() =>
          'PhabricatorDashboardPortalListController',
        $this->getEditRoutePattern('edit/') =>
          'PhabricatorDashboardPortalEditController',
        'view/(?P<portalID>\d+)/' => array(
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
