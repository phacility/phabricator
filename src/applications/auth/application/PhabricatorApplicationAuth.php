<?php

final class PhabricatorApplicationAuth extends PhabricatorApplication {

  public function shouldAppearInLaunchView() {
    return false;
  }

  public function canUninstall() {
    return false;
  }

  public function buildMainMenuItems(
    PhabricatorUser $user,
    PhabricatorController $controller = null) {

    $items = array();

    if ($user->isLoggedIn()) {
      $item = new PhabricatorMenuItemView();
      $item->setName(pht('Log Out'));
      $item->setIcon('power');
      $item->setWorkflow(true);
      $item->setHref('/logout/');
      $item->setSelected(($controller instanceof PhabricatorLogoutController));
      $items[] = $item;
    }

    return $items;
  }

}
