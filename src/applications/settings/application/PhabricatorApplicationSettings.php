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
        '(?:panel/(?P<key>[^/]+)/)?' => 'PhabricatorSettingsMainController',
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

    if ($user->isLoggedIn()) {
      $selected = ($controller instanceof PhabricatorSettingsMainController);
      $item = new PhabricatorMenuItemView();
      $item->setName(pht('Settings'));
      $item->setIcon('settings');
      $item->setSelected($selected);
      $item->setHref('/settings/');
      $item->setSortOrder(0.90);
      $items[] = $item;
    }

    return $items;
  }

}
