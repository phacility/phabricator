<?php

final class PhabricatorAuthMainMenuBarExtension
  extends PhabricatorMainMenuBarExtension {

  const MAINMENUBARKEY = 'auth';

  public function isExtensionEnabledForViewer(PhabricatorUser $viewer) {
    return true;
  }

  public function buildMainMenus() {
    $viewer = $this->getViewer();

    if ($viewer->isLoggedIn()) {
      return array(
        $this->buildLogoutMenu(),
      );
    }

    $controller = $this->getController();
    if ($controller instanceof PhabricatorAuthController) {
      // Don't show the "Login" item on auth controllers, since they're
      // generally all related to logging in anyway.
      return array();
    }

    return array(
      $this->buildLoginMenu(),
    );
  }

  private function buildLogoutMenu() {
    $controller = $this->getController();

    $is_selected = ($controller instanceof PhabricatorLogoutController);

    $bar_item = id(new PHUIListItemView())
      ->addClass('core-menu-item')
      ->setName(pht('Log Out'))
      ->setIcon('fa-sign-out')
      ->setWorkflow(true)
      ->setHref('/logout/')
      ->setSelected($is_selected)
      ->setAural(pht('Log Out'));

    return id(new PHUIMainMenuView())
      ->setOrder(900)
      ->setMenuBarItem($bar_item);
  }

  private function buildLoginMenu() {
    $controller = $this->getController();

    $uri = new PhutilURI('/auth/start/');
    if ($controller) {
      $path = $controller->getRequest()->getPath();
      $uri->setQueryParam('next', $path);
    }

    $bar_item = id(new PHUIListItemView())
      ->addClass('core-menu-item')
      ->setName(pht('Log In'))
      ->setIcon('fa-sign-in')
      ->setHref($uri)
      ->setAural(pht('Log In'));

    return id(new PHUIMainMenuView())
      ->setOrder(900)
      ->setMenuBarItem($bar_item);
  }

}
