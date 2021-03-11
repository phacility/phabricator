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

    // See T13636. This button may be rendered by the 404 controller on sites
    // other than the primary PlatformSite. Link the button to the primary
    // site.

    $uri = '/auth/start/';
    $uri = PhabricatorEnv::getURI($uri);
    $uri = new PhutilURI($uri);
    if ($controller) {
      $path = $controller->getRequest()->getPath();
      $uri->replaceQueryParam('next', $path);
    }

    return id(new PHUIButtonView())
      ->setTag('a')
      ->setText(pht('Log In'))
      ->setHref($uri)
      ->setNoCSS(true)
      ->addClass('phabricator-core-login-button');
  }

}
