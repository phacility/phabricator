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

  public function buildProfileHeader() {
    $user = $this->user;
    $viewer = $this->getViewer();

    $profile = $user->loadUserProfile();
    $picture = $user->getProfileImageURI();

    $profile_icon = PhabricatorPeopleIconSet::getIconIcon($profile->getIcon());
    $profile_title = $profile->getDisplayTitle();

    $roles = array();
    if ($user->getIsAdmin()) {
      $roles[] = pht('Administrator');
    }
    if ($user->getIsDisabled()) {
      $roles[] = pht('Disabled');
    }
    if (!$user->getIsApproved()) {
      $roles[] = pht('Not Approved');
    }
    if ($user->getIsSystemAgent()) {
      $roles[] = pht('Bot');
    }
    if ($user->getIsMailingList()) {
      $roles[] = pht('Mailing List');
    }

    $tag = null;
    if ($roles) {
      $tag = id(new PHUITagView())
        ->setName(implode(', ', $roles))
        ->addClass('project-view-header-tag')
        ->setType(PHUITagView::TYPE_SHADE);
    }

    $header = id(new PHUIHeaderView())
      ->setHeader(array($user->getFullName(), $tag))
      ->setImage($picture)
      ->setProfileHeader(true)
      ->addClass('people-profile-header');

    if ($user->getIsDisabled()) {
      $header->setStatus('fa-ban', 'red', pht('Disabled'));
    } else {
      $header->setStatus($profile_icon, 'bluegrey', $profile_title);
    }

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $user,
      PhabricatorPolicyCapability::CAN_EDIT);

    if ($can_edit) {
      $id = $user->getID();
      $header->setImageEditURL($this->getApplicationURI("picture/{$id}/"));
    }

    return $header;
  }

}
