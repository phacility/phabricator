<?php

abstract class PhabricatorPeopleProfileController
  extends PhabricatorPeopleController {

  private $user;
  private $profileMenu;

  public function shouldRequireAdmin() {
    return false;
  }

  public function setUser(PhabricatorUser $user) {
    $this->user = $user;
    return $this;
  }

  public function getUser() {
    return $this->user;
  }

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
      $user = $this->getUser();
      if ($user) {
        $viewer = $this->getViewer();

        $engine = id(new PhabricatorPeopleProfileMenuEngine())
          ->setViewer($viewer)
          ->setProfileObject($user);

        $this->profileMenu = $engine->buildNavigation();
      }
    }

    return $this->profileMenu;
  }

  protected function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    $user = $this->getUser();
    if ($user) {
      $crumbs->addTextCrumb(
        $user->getUsername(),
        urisprintf('/p/%s/', $user->getUsername()));
    }

    return $crumbs;
  }

}
