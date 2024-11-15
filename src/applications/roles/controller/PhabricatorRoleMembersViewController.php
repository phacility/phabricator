<?php

final class PhabricatorRoleMembersViewController
  extends PhabricatorRoleController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    $role = id(new PhabricatorRoleQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->needMembers(true)
      ->needWatchers(true)
      ->needImages(true)
      ->executeOne();
    if (!$role) {
      return new Aphront404Response();
    }

    $this->setRole($role);
    $title = pht('Members and Watchers');
    $curtain = $this->buildCurtainView($role);

    $member_list = id(new PhabricatorRoleMemberListView())
      ->setUser($viewer)
      ->setRole($role)
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setUserPHIDs($role->getMemberPHIDs())
      ->setShowNote(true);

    $watcher_list = id(new PhabricatorRoleWatcherListView())
      ->setUser($viewer)
      ->setRole($role)
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setUserPHIDs($role->getWatcherPHIDs())
      ->setShowNote(true);

    $nav = $this->newNavigation(
      $role,
      PhabricatorRole::ITEM_MEMBERS);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Members'));
    $crumbs->setBorder(true);

    $header = id(new PHUIHeaderView())
      ->setHeader($title)
      ->setHeaderIcon('fa-group');

    require_celerity_resource('project-view-css');

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setCurtain($curtain)
      ->addClass('project-view-home')
      ->addClass('project-view-people-home')
      ->setMainColumn(array(
        $member_list,
        $watcher_list,
      ));

    return $this->newPage()
      ->setNavigation($nav)
      ->setCrumbs($crumbs)
      ->setTitle(array($role->getName(), $title))
      ->appendChild($view);
  }

  private function buildCurtainView(PhabricatorRole $role) {
    $viewer = $this->getViewer();
    $id = $role->getID();

    $curtain = $this->newCurtainView();

    $is_locked = $role->getIsMembershipLocked();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $role,
      PhabricatorPolicyCapability::CAN_EDIT);

    $supports_edit = $role->supportsEditMembers();

    $can_join = $supports_edit && PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $role,
      PhabricatorPolicyCapability::CAN_JOIN);

    $can_leave = $supports_edit && (!$is_locked || $can_edit);

    $viewer_phid = $viewer->getPHID();

    if (!$role->isUserMember($viewer_phid)) {
      $curtain->addAction(
        id(new PhabricatorActionView())
          ->setHref('/role/update/'.$role->getID().'/join/')
          ->setIcon('fa-plus')
          ->setDisabled(!$can_join)
          ->setWorkflow(true)
          ->setName(pht('Join Role')));
    } else {
      $curtain->addAction(
        id(new PhabricatorActionView())
          ->setHref('/role/update/'.$role->getID().'/leave/')
          ->setIcon('fa-times')
          ->setDisabled(!$can_leave)
          ->setWorkflow(true)
          ->setName(pht('Leave Role')));
    }

    if (!$role->isUserWatcher($viewer->getPHID())) {
      $curtain->addAction(
        id(new PhabricatorActionView())
          ->setWorkflow(true)
          ->setHref('/role/watch/'.$role->getID().'/')
          ->setIcon('fa-eye')
          ->setName(pht('Watch Role')));
    } else {
      $curtain->addAction(
        id(new PhabricatorActionView())
          ->setWorkflow(true)
          ->setHref('/role/unwatch/'.$role->getID().'/')
          ->setIcon('fa-eye-slash')
          ->setName(pht('Unwatch Role')));
    }

    $can_silence = $role->isUserMember($viewer_phid);
    $is_silenced = $this->isRoleSilenced($role);

    if ($is_silenced) {
      $silence_text = pht('Enable Mail');
    } else {
      $silence_text = pht('Disable Mail');
    }

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName($silence_text)
        ->setIcon('fa-envelope-o')
        ->setHref("/role/silence/{$id}/")
        ->setWorkflow(true)
        ->setDisabled(!$can_silence));

    $can_add = $can_edit && $supports_edit;

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Add Members'))
        ->setIcon('fa-user-plus')
        ->setHref("/role/members/{$id}/add/")
        ->setWorkflow(true)
        ->setDisabled(!$can_add));

    $can_lock = $can_edit && $supports_edit && $this->hasApplicationCapability(
      RoleCanLockRolesCapability::CAPABILITY);

    if ($is_locked) {
      $lock_name = pht('Unlock Role');
      $lock_icon = 'fa-unlock';
    } else {
      $lock_name = pht('Lock Role');
      $lock_icon = 'fa-lock';
    }

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName($lock_name)
        ->setIcon($lock_icon)
        ->setHref($this->getApplicationURI("lock/{$id}/"))
        ->setDisabled(!$can_lock)
        ->setWorkflow(true));

    if ($role->isMilestone()) {
      $icon_key = PhabricatorRoleIconSet::getMilestoneIconKey();
      $header = PhabricatorRoleIconSet::getIconName($icon_key);
      $note = pht(
        'Members of the parent role are members of this role.');
      $show_join = false;
    } else if ($role->getHasSubroles()) {
      $header = pht('Parent Role');
      $note = pht(
        'Members of all subroles are members of this role.');
      $show_join = false;
    } else if ($role->getIsMembershipLocked()) {
      $header = pht('Locked Role');
      $note = pht(
        'Users with access may join this role, but may not leave.');
      $show_join = true;
    } else {
      $header = pht('Normal Role');
      $note = pht('Users with access may join and leave this role.');
      $show_join = true;
    }

    $curtain->newPanel()
      ->setHeaderText($header)
      ->appendChild($note);

    if ($show_join) {
      $descriptions = PhabricatorPolicyQuery::renderPolicyDescriptions(
        $viewer,
        $role);

      $curtain->newPanel()
        ->setHeaderText(pht('Joinable By'))
        ->appendChild($descriptions[PhabricatorPolicyCapability::CAN_JOIN]);
    }

    return $curtain;
  }

  private function isRoleSilenced(PhabricatorRole $role) {
    $viewer = $this->getViewer();

    $viewer_phid = $viewer->getPHID();
    if (!$viewer_phid) {
      return false;
    }

    $edge_type = PhabricatorRoleSilencedEdgeType::EDGECONST;
    $silenced = PhabricatorEdgeQuery::loadDestinationPHIDs(
      $role->getPHID(),
      $edge_type);
    $silenced = array_fuse($silenced);
    return isset($silenced[$viewer_phid]);
  }

}
