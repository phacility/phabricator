<?php

abstract class PhabricatorHomeController extends PhabricatorController {

  private $home;
  private $profileMenu;

  public function buildApplicationMenu() {
    $menu = $this->newApplicationMenu();

    $profile_menu = $this->getProfileMenu();
    if ($profile_menu) {
      $menu->setProfileMenu($profile_menu);
    }

    return $menu;
  }

  protected function getProfileMenu() {
    if (!$this->profileMenu) {
      $viewer = $this->getViewer();
      $applications = id(new PhabricatorApplicationQuery())
        ->setViewer($viewer)
        ->withClasses(array('PhabricatorHomeApplication'))
        ->withInstalled(true)
        ->execute();
      $home = head($applications);
      if (!$home) {
        return null;
      }

      $engine = id(new PhabricatorHomeProfileMenuEngine())
        ->setViewer($viewer)
        ->setProfileObject($home)
        ->setCustomPHID($viewer->getPHID());

      $this->profileMenu = $engine->buildNavigation();
    }

    return $this->profileMenu;
  }

}
