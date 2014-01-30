<?php

final class PhabricatorApplicationDashboard extends PhabricatorApplication {

  public function getBaseURI() {
    return '/dashboard/';
  }

  public function getShortDescription() {
    return pht('Such Data');
  }

  public function getIconName() {
    return 'dashboard';
  }

  public function getRoutes() {
    return array(
      '/W(?P<id>\d+)' => 'PhabricatorDashboardPanelViewController',
      '/dashboard/' => array(
        '(?:query/(?P<queryKey>[^/]+)/)?'
          => 'PhabricatorDashboardListController',
        'view/(?P<id>\d+)/' => 'PhabricatorDashboardViewController',
        'panel/' => array(
          '(?:query/(?P<queryKey>[^/]+)/)?'
            => 'PhabricatorDashboardPanelListController',
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
