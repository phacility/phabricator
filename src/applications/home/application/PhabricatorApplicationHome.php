<?php

final class PhabricatorApplicationHome extends PhabricatorApplication {

  public function getBaseURI() {
    return '/';
  }

  public function getShortDescription() {
    return pht('Where the Heart Is');
  }

  public function getIconName() {
    return 'home';
  }

  public function getRoutes() {
    return array(
      '/(?:(?P<filter>(?:jump))/)?' => 'PhabricatorHomeMainController',
      '/home/' => array(
        'create/' => 'PhabricatorHomeQuickCreateController',
      ),
    );
  }

  public function shouldAppearInLaunchView() {
    return false;
  }

  public function canUninstall() {
    return false;
  }

  public function getApplicationOrder() {
    return 9;
  }

  public function buildMainMenuItems(
    PhabricatorUser $user,
    PhabricatorController $controller = null) {

    $items = array();

    if ($user->isLoggedIn() && $user->isUserActivated()) {
      $item = id(new PHUIListItemView())
        ->setName(pht('Create New...'))
        ->setIcon('new')
        ->addClass('core-menu-item')
        ->setHref('/home/create/')
        ->setOrder(300);
      $items[] = $item;
    }

    return $items;
  }

}
