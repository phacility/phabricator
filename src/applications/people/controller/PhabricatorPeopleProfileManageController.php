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
    $header = $this->buildProfileHeader();

    $curtain = $this->buildCurtain($user);
    $properties = $this->buildPropertyView($user);
    $name = $user->getUsername();

    $nav = $this->newNavigation(
      $user,
      PhabricatorPeopleProfileMenuEngine::ITEM_MANAGE);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Manage'));
    $crumbs->setBorder(true);

    $timeline = $this->buildTransactionTimeline(
      $user,
      new PhabricatorPeopleTransactionQuery());
    $timeline->setShouldTerminate(true);

    $manage = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->addClass('project-view-home')
      ->addClass('project-view-people-home')
      ->setCurtain($curtain)
      ->addPropertySection(pht('Details'), $properties)
      ->setMainColumn($timeline);

    return $this->newPage()
      ->setTitle(
        array(
          pht('Manage User'),
          $user->getUsername(),
        ))
      ->setNavigation($nav)
      ->setCrumbs($crumbs)
      ->appendChild($manage);
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

    $is_self = ($user->getPHID() === $viewer->getPHID());

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $user,
      PhabricatorPolicyCapability::CAN_EDIT);

    $is_admin = $viewer->getIsAdmin();
    $can_admin = ($is_admin && !$is_self);

    $has_disable = $this->hasApplicationCapability(
      PeopleDisableUsersCapability::CAPABILITY);
    $can_disable = ($has_disable && !$is_self);

    $id = $user->getID();

    $welcome_engine = id(new PhabricatorPeopleWelcomeMailEngine())
      ->setSender($viewer)
      ->setRecipient($user);

    $can_welcome = $welcome_engine->canSendMail();
    $curtain = $this->newCurtainView($user);

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setIcon('fa-pencil')
        ->setName(pht('Edit Profile'))
        ->setHref($this->getApplicationURI('editprofile/'.$id.'/'))
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit));

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setIcon('fa-picture-o')
        ->setName(pht('Edit Profile Picture'))
        ->setHref($this->getApplicationURI('picture/'.$id.'/'))
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

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setIcon($empower_icon)
        ->setName($empower_name)
        ->setDisabled(!$can_admin)
        ->setWorkflow(true)
        ->setHref($this->getApplicationURI('empower/'.$id.'/')));

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setIcon('fa-tag')
        ->setName(pht('Change Username'))
        ->setDisabled(!$is_admin)
        ->setWorkflow(true)
        ->setHref($this->getApplicationURI('rename/'.$id.'/')));

    if ($user->getIsDisabled()) {
      $disable_icon = 'fa-check-circle-o';
      $disable_name = pht('Enable User');
    } else {
      $disable_icon = 'fa-ban';
      $disable_name = pht('Disable User');
    }

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setIcon('fa-envelope')
        ->setName(pht('Send Welcome Email'))
        ->setWorkflow(true)
        ->setDisabled(!$can_welcome)
        ->setHref($this->getApplicationURI('welcome/'.$id.'/')));

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setType(PhabricatorActionView::TYPE_DIVIDER));

    if (!$user->getIsApproved()) {
      $approve_action = id(new PhabricatorActionView())
        ->setIcon('fa-thumbs-up')
        ->setName(pht('Approve User'))
        ->setWorkflow(true)
        ->setDisabled(!$is_admin)
        ->setHref("/people/approve/{$id}/via/profile/");

      if ($is_admin) {
        $approve_action->setColor(PhabricatorActionView::GREEN);
      }

      $curtain->addAction($approve_action);
    }

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setIcon($disable_icon)
        ->setName($disable_name)
        ->setDisabled(!$can_disable)
        ->setWorkflow(true)
        ->setHref($this->getApplicationURI('disable/'.$id.'/')));

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setIcon('fa-times')
        ->setName(pht('Delete User'))
        ->setDisabled(!$can_admin)
        ->setWorkflow(true)
        ->setHref($this->getApplicationURI('delete/'.$id.'/')));

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setType(PhabricatorActionView::TYPE_DIVIDER));

    return $curtain;
  }


}
