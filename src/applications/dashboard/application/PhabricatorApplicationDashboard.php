<?php

final class PhabricatorApplicationDashboard extends PhabricatorApplication {

  public function getBaseURI() {
    return '/dashboard/';
  }

  public function getShortDescription() {
    return pht('Such Data');
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
        'create/' => 'PhabricatorDashboardEditController',
        'edit/(?:(?P<id>\d+)/)?' => 'PhabricatorDashboardEditController',
        'addpanel/(?P<id>\d+)/' => 'PhabricatorDashboardAddPanelController',

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

  public function shouldAppearInLaunchView() {
    return false;
  }

  public function canUninstall() {
    return false;
  }

}
