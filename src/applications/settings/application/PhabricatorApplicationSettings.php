<?php

final class PhabricatorApplicationSettings extends PhabricatorApplication {

  public function getBaseURI() {
    return '/settings/';
  }

  public function getShortDescription() {
    return 'User Preferences';
  }

  public function getAutospriteName() {
    return 'settings';
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

    if ($controller instanceof PhabricatorSettingsMainController) {
      $class = 'main-menu-item-icon-settings-selected';
    } else {
      $class = 'main-menu-item-icon-settings';
    }

    if ($user->isLoggedIn()) {
      $item = new PhabricatorMainMenuIconView();
      $item->setName(pht('Settings'));
      $item->addClass('autosprite main-menu-item-icon '.$class);
      $item->setHref('/settings/');
      $item->setSortOrder(0.90);
      $items[] = $item;
    }

    return $items;
  }

}
