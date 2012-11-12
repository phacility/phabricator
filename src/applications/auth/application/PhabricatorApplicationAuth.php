<?php

final class PhabricatorApplicationAuth extends PhabricatorApplication {

  public function shouldAppearInLaunchView() {
    return false;
  }

  public function buildMainMenuItems(
    PhabricatorUser $user,
    PhabricatorController $controller = null) {

    $items = array();

    if ($controller instanceof PhabricatorLogoutController) {
      $class = 'main-menu-item-icon-logout-selected';
    } else {
      $class = 'main-menu-item-icon-logout';
    }

    if ($user->isLoggedIn()) {
      $item = new PhabricatorMainMenuIconView();
      $item->setName(pht('Log Out'));
      $item->addClass('autosprite main-menu-item-icon '.$class);
      $item->setWorkflow(true);
      $item->setHref('/logout/');
      $item->setSortOrder(1.0);
      $items[] = $item;
    }

    return $items;
  }

}
