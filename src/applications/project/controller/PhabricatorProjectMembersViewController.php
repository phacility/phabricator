<?php

final class PhabricatorProjectMembersViewController
  extends PhabricatorProjectController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    $project = id(new PhabricatorProjectQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->needMembers(true)
      ->needWatchers(true)
      ->needImages(true)
      ->executeOne();
    if (!$project) {
      return new Aphront404Response();
    }

    $this->setProject($project);
    $title = pht('Members and Watchers');

    $properties = $this->buildProperties($project);
    $actions = $this->buildActions($project);
    $properties->setActionList($actions);

    $object_box = id(new PHUIObjectBoxView())
      ->setHeaderText($title)
      ->addPropertyList($properties);

    $member_list = id(new PhabricatorProjectMemberListView())
      ->setUser($viewer)
      ->setProject($project)
      ->setUserPHIDs($project->getMemberPHIDs());

    $watcher_list = id(new PhabricatorProjectWatcherListView())
      ->setUser($viewer)
      ->setProject($project)
      ->setUserPHIDs($project->getWatcherPHIDs());

    $nav = $this->getProfileMenu();
    $nav->selectFilter(PhabricatorProject::PANEL_MEMBERS);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Members'));

    return $this->newPage()
      ->setNavigation($nav)
      ->setCrumbs($crumbs)
      ->setTitle(array($project->getName(), $title))
      ->appendChild(
        array(
          $object_box,
          $member_list,
          $watcher_list,
        ));
  }

  private function buildProperties(PhabricatorProject $project) {
    $viewer = $this->getViewer();

    $view = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->setObject($project);

    if ($project->isMilestone()) {
      $icon_key = PhabricatorProjectIconSet::getMilestoneIconKey();
      $icon = PhabricatorProjectIconSet::getIconIcon($icon_key);
      $target = PhabricatorProjectIconSet::getIconName($icon_key);
      $note = pht(
        'Members of the parent project are members of this project.');
      $show_join = false;
    } else if ($project->getHasSubprojects()) {
      $icon = 'fa-sitemap';
      $target = pht('Parent Project');
      $note = pht(
        'Members of all subprojects are members of this project.');
      $show_join = false;
    } else if ($project->getIsMembershipLocked()) {
      $icon = 'fa-lock';
      $target = pht('Locked Project');
      $note = pht(
        'Users with access may join this project, but may not leave.');
      $show_join = true;
    } else {
      $icon = 'fa-briefcase';
      $target = pht('Normal Project');
      $note = pht('Users with access may join and leave this project.');
      $show_join = true;
    }

    $item = id(new PHUIStatusItemView())
      ->setIcon($icon)
      ->setTarget(phutil_tag('strong', array(), $target))
      ->setNote($note);

    $status = id(new PHUIStatusListView())
      ->addItem($item);

    $view->addProperty(pht('Membership'), $status);

    if ($show_join) {
      $descriptions = PhabricatorPolicyQuery::renderPolicyDescriptions(
        $viewer,
        $project);

      $view->addProperty(
        pht('Joinable By'),
        $descriptions[PhabricatorPolicyCapability::CAN_JOIN]);
    }

    return $view;
  }

  private function buildActions(PhabricatorProject $project) {
    $viewer = $this->getViewer();
    $id = $project->getID();

    $view = id(new PhabricatorActionListView())
      ->setUser($viewer);

    $is_locked = $project->getIsMembershipLocked();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $project,
      PhabricatorPolicyCapability::CAN_EDIT);

    $supports_edit = $project->supportsEditMembers();

    $can_join = $supports_edit && PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $project,
      PhabricatorPolicyCapability::CAN_JOIN);

    $can_leave = $supports_edit && (!$is_locked || $can_edit);

    if (!$project->isUserMember($viewer->getPHID())) {
      $view->addAction(
        id(new PhabricatorActionView())
          ->setHref('/project/update/'.$project->getID().'/join/')
          ->setIcon('fa-plus')
          ->setDisabled(!$can_join)
          ->setWorkflow(true)
          ->setName(pht('Join Project')));
    } else {
      $view->addAction(
        id(new PhabricatorActionView())
          ->setHref('/project/update/'.$project->getID().'/leave/')
          ->setIcon('fa-times')
          ->setDisabled(!$can_leave)
          ->setWorkflow(true)
          ->setName(pht('Leave Project')));
    }

    if (!$project->isUserWatcher($viewer->getPHID())) {
      $view->addAction(
        id(new PhabricatorActionView())
          ->setWorkflow(true)
          ->setHref('/project/watch/'.$project->getID().'/')
          ->setIcon('fa-eye')
          ->setName(pht('Watch Project')));
    } else {
      $view->addAction(
        id(new PhabricatorActionView())
          ->setWorkflow(true)
          ->setHref('/project/unwatch/'.$project->getID().'/')
          ->setIcon('fa-eye-slash')
          ->setName(pht('Unwatch Project')));
    }

    $can_add = $can_edit && $supports_edit;

    $view->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Add Members'))
        ->setIcon('fa-user-plus')
        ->setHref("/project/members/{$id}/add/")
        ->setWorkflow(true)
        ->setDisabled(!$can_add));

    $can_lock = $can_edit && $supports_edit && $this->hasApplicationCapability(
      ProjectCanLockProjectsCapability::CAPABILITY);

    if ($is_locked) {
      $lock_name = pht('Unlock Project');
      $lock_icon = 'fa-unlock';
    } else {
      $lock_name = pht('Lock Project');
      $lock_icon = 'fa-lock';
    }

    $view->addAction(
      id(new PhabricatorActionView())
        ->setName($lock_name)
        ->setIcon($lock_icon)
        ->setHref($this->getApplicationURI("lock/{$id}/"))
        ->setDisabled(!$can_lock)
        ->setWorkflow(true));

    return $view;
  }

}
