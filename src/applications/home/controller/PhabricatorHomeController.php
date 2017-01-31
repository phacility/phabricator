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
        ->setProfileObject($home);

      if ($viewer->getPHID()) {
        $engine->setCustomPHID($viewer->getPHID())
          ->setMenuType(PhabricatorProfileMenuEngine::MENU_COMBINED);
      } else {
        $engine->setMenuType(PhabricatorProfileMenuEngine::MENU_GLOBAL);
      }

      $this->profileMenu = $engine->buildNavigation();
    }

    return $this->profileMenu;
  }

}
