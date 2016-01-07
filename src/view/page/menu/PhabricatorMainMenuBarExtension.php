<?php

abstract class PhabricatorMainMenuBarExtension extends Phobject {

  private $viewer;
  private $application;
  private $controller;

  public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  public function getViewer() {
    return $this->viewer;
  }

  public function setApplication(PhabricatorApplication $application) {
    $this->application = $application;
    return $this;
  }

  public function getApplication() {
    return $this->application;
  }

  public function setController(PhabricatorController $controller) {
    $this->controller = $controller;
    return $this;
  }

  public function getController() {
    return $this->controller;
  }

  final public function getExtensionKey() {
    return $this->getPhobjectClassConstant('MAINMENUBARKEY');
  }

  public function isExtensionEnabled() {
    return true;
  }

  public function isExtensionEnabledForViewer(PhabricatorUser $viewer) {
    if (!$viewer->isLoggedIn()) {
      return false;
    }

    if (!$viewer->isUserActivated()) {
      return false;
    }

    // Don't show menus for users with partial sessions. This usually means
    // they have logged in but have not made it through MFA, so we don't want
    // to show notification counts, saved queries, etc.
    if (!$viewer->hasSession()) {
      return false;
    }

    if ($viewer->getSession()->getIsPartial()) {
      return false;
    }

    return true;
  }

  abstract public function buildMainMenus();

  final public static function getAllExtensions() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getExtensionKey')
      ->execute();
  }

  final public static function getAllEnabledExtensions() {
    $extensions = self::getAllExtensions();

    foreach ($extensions as $key => $extension) {
      if (!$extension->isExtensionEnabled()) {
        unset($extensions[$key]);
      }
    }

    return $extensions;
  }

}
