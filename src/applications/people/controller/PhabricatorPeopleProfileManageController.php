<?php

final class PhabricatorPeopleProfileManageController
  extends PhabricatorPeopleProfileController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();
    $id = $request->getURIData('id');

    $user = id(new PhabricatorPeopleQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->needProfile(true)
      ->needProfileImage(true)
      ->needAvailability(true)
      ->executeOne();
    if (!$user) {
      return new Aphront404Response();
    }

    $this->setUser($user);

    $profile = $user->loadUserProfile();
    $picture = $user->getProfileImageURI();

    $profile_icon = PhabricatorPeopleIconSet::getIconIcon($profile->getIcon());
    $profile_icon = id(new PHUIIconView())
      ->setIcon($profile_icon);
    $profile_title = $profile->getDisplayTitle();

    $header = id(new PHUIHeaderView())
      ->setHeader($user->getFullName())
      ->setSubheader(array($profile_icon, $profile_title))
      ->setImage($picture)
      ->setProfileHeader(true);

    $curtain = $this->buildCurtain($user);
    $properties = $this->buildPropertyView($user);
    $name = $user->getUsername();

    $nav = $this->getProfileMenu();
    $nav->selectFilter(PhabricatorPeopleProfilePanelEngine::PANEL_MANAGE);

    $timeline = $this->buildTransactionTimeline(
      $user,
      new PhabricatorPeopleTransactionQuery());
    $timeline->setShouldTerminate(true);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Manage'));
    $crumbs->setBorder(true);

    $manage = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setCurtain($curtain)
      ->addPropertySection(pht('Details'), $properties)
      ->setMainColumn(
        array(
          $timeline,
        ));

    return $this->newPage()
      ->setTitle(
        array(
          pht('Manage User'),
          $user->getUsername(),
        ))
      ->setNavigation($nav)
      ->setCrumbs($crumbs)
      ->appendChild(
        array(
          $manage,
        ));
  }

  private function buildPropertyView(PhabricatorUser $user) {

    $viewer = $this->getRequest()->getUser();
    $view = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->setObject($user);

    $field_list = PhabricatorCustomField::getObjectFields(
      $user,
      PhabricatorCustomField::ROLE_VIEW);
    $field_list->appendFieldsToPropertyList($user, $viewer, $view);

    return $view;
  }

  private function buildCurtain(PhabricatorUser $user) {
    $viewer = $this->getViewer();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $user,
      PhabricatorPolicyCapability::CAN_EDIT);

    $curtain = $this->newCurtainView($user);

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setIcon('fa-pencil')
        ->setName(pht('Edit Profile'))
        ->setHref($this->getApplicationURI('editprofile/'.$user->getID().'/'))
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit));

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setIcon('fa-picture-o')
        ->setName(pht('Edit Profile Picture'))
        ->setHref($this->getApplicationURI('picture/'.$user->getID().'/'))
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit));

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setIcon('fa-wrench')
        ->setName(pht('Edit Settings'))
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit)
        ->setHref('/settings/user/'.$user->getUsername().'/'));

    if ($user->getIsAdmin()) {
      $empower_icon = 'fa-arrow-circle-o-down';
      $empower_name = pht('Remove Administrator');
    } else {
      $empower_icon = 'fa-arrow-circle-o-up';
      $empower_name = pht('Make Administrator');
    }

    $is_admin = $viewer->getIsAdmin();
    $is_self = ($user->getPHID() === $viewer->getPHID());
    $can_admin = ($is_admin && !$is_self);

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setIcon($empower_icon)
        ->setName($empower_name)
        ->setDisabled(!$can_admin)
        ->setWorkflow(true)
        ->setHref($this->getApplicationURI('empower/'.$user->getID().'/')));

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setIcon('fa-tag')
        ->setName(pht('Change Username'))
        ->setDisabled(!$is_admin)
        ->setWorkflow(true)
        ->setHref($this->getApplicationURI('rename/'.$user->getID().'/')));

    if ($user->getIsDisabled()) {
      $disable_icon = 'fa-check-circle-o';
      $disable_name = pht('Enable User');
    } else {
      $disable_icon = 'fa-ban';
      $disable_name = pht('Disable User');
    }

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setIcon($disable_icon)
        ->setName($disable_name)
        ->setDisabled(!$can_admin)
        ->setWorkflow(true)
        ->setHref($this->getApplicationURI('disable/'.$user->getID().'/')));

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setIcon('fa-times')
        ->setName(pht('Delete User'))
        ->setDisabled(!$can_admin)
        ->setWorkflow(true)
        ->setHref($this->getApplicationURI('delete/'.$user->getID().'/')));

    $can_welcome = ($is_admin && $user->canEstablishWebSessions());

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setIcon('fa-envelope')
        ->setName(pht('Send Welcome Email'))
        ->setWorkflow(true)
        ->setDisabled(!$can_welcome)
        ->setHref($this->getApplicationURI('welcome/'.$user->getID().'/')));

    return $curtain;
  }


}
