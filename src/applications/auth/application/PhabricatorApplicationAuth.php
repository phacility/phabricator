<?php

final class PhabricatorApplicationAuth extends PhabricatorApplication {

  public function shouldAppearInLaunchView() {
    return false;
  }

  public function canUninstall() {
    return false;
  }

  public function getBaseURI() {
    return '/auth/';
  }

  public function buildMainMenuItems(
    PhabricatorUser $user,
    PhabricatorController $controller = null) {

    $items = array();

    if ($user->isLoggedIn()) {
      $item = new PHUIListItemView();
      $item->setName(pht('Log Out'));
      $item->setIcon('power');
      $item->setWorkflow(true);
      $item->setHref('/logout/');
      $item->setSelected(($controller instanceof PhabricatorLogoutController));
      $items[] = $item;
    }

    return $items;
  }

  public function getRoutes() {
    return array(
      '/auth/' => array(
        'login/(?P<pkey>[^/]+)/' => 'PhabricatorAuthLoginController',
        'register/(?P<akey>[^/]+)/' => 'PhabricatorAuthRegisterController',
        'start/' => 'PhabricatorAuthStartController',
        'validate/' => 'PhabricatorAuthValidateController',
      ),
    );
  }

}
