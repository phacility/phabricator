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
    $curtain = $this->buildCurtainView($project);

    $object_box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Details'))
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->addPropertyList($properties);

    $member_list = id(new PhabricatorProjectMemberListView())
      ->setUser($viewer)
      ->setProject($project)
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setUserPHIDs($project->getMemberPHIDs());

    $watcher_list = id(new PhabricatorProjectWatcherListView())
      ->setUser($viewer)
      ->setProject($project)
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setUserPHIDs($project->getWatcherPHIDs());

    $nav = $this->getProfileMenu();
    $nav->selectFilter(PhabricatorProject::ITEM_MEMBERS);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Members'));
    $crumbs->setBorder(true);

    $header = id(new PHUIHeaderView())
      ->setHeader($title)
      ->setHeaderIcon('fa-group');

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setCurtain($curtain)
      ->setMainColumn(array(
        $object_box,
        $member_list,
        $watcher_list,
      ));


    return $this->newPage()
      ->setNavigation($nav)
      ->setCrumbs($crumbs)
      ->setTitle(array($project->getName(), $title))
      ->appendChild($view);
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

    $viewer_phid = $viewer->getPHID();

    if ($project->isUserWatcher($viewer_phid)) {
      $watch_item = id(new PHUIStatusItemView())
        ->setIcon('fa-eye green')
        ->setTarget(phutil_tag('strong', array(), pht('Watching')))
        ->setNote(
          pht(
            'You will receive mail about changes made to any related '.
            'object.'));

      $watch_status = id(new PHUIStatusListView())
        ->addItem($watch_item);

      $view->addProperty(pht('Watching'), $watch_status);
    }

    if ($project->isUserMember($viewer_phid)) {
      $is_silenced = $this->isProjectSilenced($project);
      if ($is_silenced) {
        $mail_icon = 'fa-envelope-o grey';
        $mail_target = pht('Disabled');
        $mail_note = pht(
          'When mail is sent to project members, you will not receive '.
          'a copy.');
      } else {
        $mail_icon = 'fa-envelope-o green';
        $mail_target = pht('Enabled');
        $mail_note = pht(
          'You will receive mail that is sent to project members.');
      }

      $mail_item = id(new PHUIStatusItemView())
        ->setIcon($mail_icon)
        ->setTarget(phutil_tag('strong', array(), $mail_target))
        ->setNote($mail_note);

      $mail_status = id(new PHUIStatusListView())
        ->addItem($mail_item);

      $view->addProperty(pht('Mail to Members'), $mail_status);
    }

    return $view;
  }

  private function buildCurtainView(PhabricatorProject $project) {
    $viewer = $this->getViewer();
    $id = $project->getID();

    $curtain = $this->newCurtainView($project);

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

    $viewer_phid = $viewer->getPHID();

    if (!$project->isUserMember($viewer_phid)) {
      $curtain->addAction(
        id(new PhabricatorActionView())
          ->setHref('/project/update/'.$project->getID().'/join/')
          ->setIcon('fa-plus')
          ->setDisabled(!$can_join)
          ->setWorkflow(true)
          ->setName(pht('Join Project')));
    } else {
      $curtain->addAction(
        id(new PhabricatorActionView())
          ->setHref('/project/update/'.$project->getID().'/leave/')
          ->setIcon('fa-times')
          ->setDisabled(!$can_leave)
          ->setWorkflow(true)
          ->setName(pht('Leave Project')));
    }

    if (!$project->isUserWatcher($viewer->getPHID())) {
      $curtain->addAction(
        id(new PhabricatorActionView())
          ->setWorkflow(true)
          ->setHref('/project/watch/'.$project->getID().'/')
          ->setIcon('fa-eye')
          ->setName(pht('Watch Project')));
    } else {
      $curtain->addAction(
        id(new PhabricatorActionView())
          ->setWorkflow(true)
          ->setHref('/project/unwatch/'.$project->getID().'/')
          ->setIcon('fa-eye-slash')
          ->setName(pht('Unwatch Project')));
    }

    $can_silence = $project->isUserMember($viewer_phid);
    $is_silenced = $this->isProjectSilenced($project);

    if ($is_silenced) {
      $silence_text = pht('Enable Mail');
    } else {
      $silence_text = pht('Disable Mail');
    }

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName($silence_text)
        ->setIcon('fa-envelope-o')
        ->setHref("/project/silence/{$id}/")
        ->setWorkflow(true)
        ->setDisabled(!$can_silence));

    $can_add = $can_edit && $supports_edit;

    $curtain->addAction(
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

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName($lock_name)
        ->setIcon($lock_icon)
        ->setHref($this->getApplicationURI("lock/{$id}/"))
        ->setDisabled(!$can_lock)
        ->setWorkflow(true));

    return $curtain;
  }

  private function isProjectSilenced(PhabricatorProject $project) {
    $viewer = $this->getViewer();

    $viewer_phid = $viewer->getPHID();
    if (!$viewer_phid) {
      return false;
    }

    $edge_type = PhabricatorProjectSilencedEdgeType::EDGECONST;
    $silenced = PhabricatorEdgeQuery::loadDestinationPHIDs(
      $project->getPHID(),
      $edge_type);
    $silenced = array_fuse($silenced);
    return isset($silenced[$viewer_phid]);
  }

}
