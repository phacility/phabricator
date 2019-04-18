<?php

abstract class PhabricatorPeopleProfileController
  extends PhabricatorPeopleController {

  private $user;

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


    $tag = id(new PHUITagView())
      ->setType(PHUITagView::TYPE_SHADE);

    $tags = array();
    if ($user->getIsAdmin()) {
      $tags[] = id(clone $tag)
        ->setName(pht('Administrator'))
        ->setColor('blue');
    }

    // "Disabled" gets a stronger status tag below.

    if (!$user->getIsApproved()) {
      $tags[] = id(clone $tag)
        ->setName('Not Approved')
        ->setColor('yellow');
    }

    if ($user->getIsSystemAgent()) {
      $tags[] = id(clone $tag)
        ->setName(pht('Bot'))
        ->setColor('orange');
    }

    if ($user->getIsMailingList()) {
      $tags[] = id(clone $tag)
        ->setName(pht('Mailing List'))
        ->setColor('orange');
    }

    if (!$user->getIsEmailVerified()) {
      $tags[] = id(clone $tag)
        ->setName(pht('Email Not Verified'))
        ->setColor('violet');
    }

    $header = id(new PHUIHeaderView())
      ->setHeader($user->getFullName())
      ->setImage($picture)
      ->setProfileHeader(true)
      ->addClass('people-profile-header');

    foreach ($tags as $tag) {
      $header->addTag($tag);
    }

    require_celerity_resource('project-view-css');

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

  final protected function newNavigation(
    PhabricatorUser $user,
    $item_identifier) {

    $viewer = $this->getViewer();

    $engine = id(new PhabricatorPeopleProfileMenuEngine())
      ->setViewer($viewer)
      ->setController($this)
      ->setProfileObject($user);

    $view_list = $engine->newProfileMenuItemViewList();

    $view_list->setSelectedViewWithItemIdentifier($item_identifier);

    $navigation = $view_list->newNavigationView();

    return $navigation;
  }

}
