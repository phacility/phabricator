<?php

final class PhabricatorApplicationSettings extends PhabricatorApplication {

  public function getBaseURI() {
    return '/settings/';
  }

  public function getShortDescription() {
    return 'User Preferences';
  }

  public function getIconName() {
    return 'settings';
  }

  public function canUninstall() {
    return false;
  }

  public function getRoutes() {
    return array(
      '/settings/' => array(
        '(?:(?P<id>\d+)/)?(?:panel/(?P<key>[^/]+)/)?'
          => 'PhabricatorSettingsMainController',
        'adjust/' => 'PhabricatorSettingsAdjustController',
      ),
    );
  }

  public function getApplicationGroup() {
    return self::GROUP_UTILITIES;
  }

  public function buildMainMenuItems(
    PhabricatorUser $user,
    PhabricatorController $controller = null) {

    $items = array();

    if ($user->isLoggedIn() && $user->isUserActivated()) {
      $selected = ($controller instanceof PhabricatorSettingsMainController);
      $item = id(new PHUIListItemView())
        ->setName(pht('Settings'))
        ->setIcon('settings-sm')
        ->addClass('core-menu-item')
        ->setSelected($selected)
        ->setHref('/settings/')
        ->setAural(pht('Settings'))
        ->setOrder(400);
      $items[] = $item;
    }

    return $items;
  }

}
