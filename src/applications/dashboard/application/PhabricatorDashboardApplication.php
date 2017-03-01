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

  public function getIcon() {
    return 'fa-dashboard';
  }

  public function getRoutes() {
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
        'addpanel/(?P<id>\d+)/' => 'PhabricatorDashboardAddPanelController',
        'movepanel/(?P<id>\d+)/' => 'PhabricatorDashboardMovePanelController',
        'removepanel/(?P<id>\d+)/'
          => 'PhabricatorDashboardRemovePanelController',
        'panel/' => array(
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
    );
  }

  public function getRemarkupRules() {
    return array(
      new PhabricatorDashboardRemarkupRule(),
    );
  }

}
