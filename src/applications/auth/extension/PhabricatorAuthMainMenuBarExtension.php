<?php

final class PhabricatorAuthMainMenuBarExtension
  extends PhabricatorMainMenuBarExtension {

  const MAINMENUBARKEY = 'auth';

  public function isExtensionEnabledForViewer(PhabricatorUser $viewer) {
    return true;
  }

  public function shouldRequireFullSession() {
    return false;
  }

  public function getExtensionOrder() {
    return 900;
  }

  public function buildMainMenus() {
    $viewer = $this->getViewer();

    if ($viewer->isLoggedIn()) {
      return array();
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

  private function buildLoginMenu() {
    $controller = $this->getController();

    $uri = new PhutilURI('/auth/start/');
    if ($controller) {
      $path = $controller->getRequest()->getPath();
      $uri->setQueryParam('next', $path);
    }

    return id(new PHUIButtonView())
      ->setTag('a')
      ->setText(pht('Log In'))
      ->setHref($uri)
      ->setNoCSS(true)
      ->addClass('phabricator-core-login-button');
  }

}
