<?php

final class PhabricatorApplicationAuth extends PhabricatorApplication {

  public function shouldAppearInLaunchView() {
    return false;
  }

  public function buildMainMenuItems(
    PhabricatorUser $user,
    PhabricatorController $controller = null) {

    $items = array();

    if ($user->isLoggedIn()) {
      $item = new PhabricatorMenuItemView();
      $item->setName(pht('Log Out'));
      $item->setIcon('logout');
      $item->setWorkflow(true);
      $item->setHref('/logout/');
      $item->setSortOrder(2.0);
      $item->setSelected(($controller instanceof PhabricatorLogoutController));
      $items[] = $item;
    }

    return $items;
  }

}
