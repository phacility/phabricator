<?php

final class PhabricatorPeopleProfileViewController
  extends PhabricatorPeopleProfileController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();
    $username = $request->getURIData('username');

    $user = id(new PhabricatorPeopleQuery())
      ->setViewer($viewer)
      ->withUsernames(array($username))
      ->needBadges(true)
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

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $user,
      PhabricatorPolicyCapability::CAN_EDIT);

    if ($can_edit) {
      $id = $user->getID();
      $header->setImageEditURL($this->getApplicationURI("picture/{$id}/"));
    }

    $properties = $this->buildPropertyView($user);
    $name = $user->getUsername();

    $feed = $this->buildPeopleFeed($user, $viewer);
    $feed = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Recent Activity'))
      ->addClass('project-view-feed')
      ->appendChild($feed);

    $projects = $this->buildProjectsView($user);
    $badges = $this->buildBadgesView($user);
    $calendar = $this->buildCalendarDayView($user);
    require_celerity_resource('project-view-css');

    $home = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->addClass('project-view-home')
      ->setMainColumn(
        array(
          $properties,
          $feed,
        ))
      ->setSideColumn(
        array(
          $projects,
          $badges,
          $calendar,
        ));

    $nav = $this->getProfileMenu();
    $nav->selectFilter(PhabricatorPeopleProfilePanelEngine::PANEL_PROFILE);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->setBorder(true);

    return $this->newPage()
      ->setTitle($user->getUsername())
      ->setNavigation($nav)
      ->setCrumbs($crumbs)
      ->appendChild(
        array(
          $home,
        ));
  }

  private function buildPropertyView(
    PhabricatorUser $user) {

    $viewer = $this->getRequest()->getUser();
    $view = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->setObject($user);

    $field_list = PhabricatorCustomField::getObjectFields(
      $user,
      PhabricatorCustomField::ROLE_VIEW);
    $field_list->appendFieldsToPropertyList($user, $viewer, $view);

    if (!$view->hasAnyProperties()) {
      return null;
    }

    $view = id(new PHUIObjectBoxView())
      ->appendChild($view)
      ->addClass('project-view-properties');

    return $view;
  }

  private function buildProjectsView(
    PhabricatorUser $user) {

    $viewer = $this->getViewer();
    $projects = id(new PhabricatorProjectQuery())
      ->setViewer($viewer)
      ->withMemberPHIDs(array($user->getPHID()))
      ->needImages(true)
      ->withStatuses(
        array(
          PhabricatorProjectStatus::STATUS_ACTIVE,
        ))
      ->execute();

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Projects'));

    if (!empty($projects)) {
      $limit = 5;
      $render_phids = array_slice($projects, 0, $limit);
      $list = id(new PhabricatorProjectListView())
        ->setUser($viewer)
        ->setProjects($render_phids);

      if (count($projects) > $limit) {
        $header_text = pht(
          'Projects (%s)',
          phutil_count($projects));

        $header = id(new PHUIHeaderView())
          ->setHeader($header_text)
          ->addActionLink(
            id(new PHUIButtonView())
              ->setTag('a')
              ->setIcon('fa-list-ul')
              ->setText(pht('View All'))
              ->setHref('/project/?member='.$user->getPHID()));

      }

    } else {
      $error = id(new PHUIBoxView())
        ->addClass('mlb')
        ->appendChild(pht('User does not belong to any projects.'));
      $list = id(new PHUIInfoView())
        ->setSeverity(PHUIInfoView::SEVERITY_NODATA)
        ->appendChild($error);
    }

    $box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->appendChild($list)
      ->setBackground(PHUIObjectBoxView::GREY);

    return $box;
  }

  private function buildCalendarDayView(PhabricatorUser $user) {
    $viewer = $this->getViewer();
    $class = 'PhabricatorCalendarApplication';

    if (!PhabricatorApplication::isClassInstalledForViewer($class, $viewer)) {
      return null;
    }

    $midnight = PhabricatorTime::getTodayMidnightDateTime($viewer);
    $week_end = clone $midnight;
    $week_end = $week_end->modify('+3 days');

    $range_start = $midnight->format('U');
    $range_end = $week_end->format('U');

    $events = id(new PhabricatorCalendarEventQuery())
      ->setViewer($viewer)
      ->withDateRange($range_start, $range_end)
      ->withInvitedPHIDs(array($user->getPHID()))
      ->withIsCancelled(false)
      ->execute();

    $event_views = array();
    foreach ($events as $event) {
      $viewer_is_invited = $event->getIsUserInvited($viewer->getPHID());

      $can_edit = PhabricatorPolicyFilter::hasCapability(
        $viewer,
        $event,
        PhabricatorPolicyCapability::CAN_EDIT);

      $epoch_min = $event->getStartDateTimeEpoch();
      $epoch_max = $event->getEndDateTimeEpoch();

      $event_view = id(new AphrontCalendarEventView())
        ->setCanEdit($can_edit)
        ->setEventID($event->getID())
        ->setEpochRange($epoch_min, $epoch_max)
        ->setIsAllDay($event->getIsAllDay())
        ->setIcon($event->getIcon())
        ->setViewerIsInvited($viewer_is_invited)
        ->setName($event->getName())
        ->setURI($event->getURI());

      $event_views[] = $event_view;
    }

    $event_views = msort($event_views, 'getEpochStart');

    $day_view = id(new PHUICalendarWeekView())
      ->setViewer($viewer)
      ->setView('week')
      ->setEvents($event_views)
      ->setWeekLength(3)
      ->render();

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Calendar'))
      ->setHref(
        urisprintf(
          '/calendar/?invited=%s#R',
          $user->getUsername()));

    $box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->appendChild($day_view)
      ->addClass('calendar-profile-box')
      ->setBackground(PHUIObjectBoxView::GREY);

    return $box;
  }

  private function buildBadgesView(PhabricatorUser $user) {

    $viewer = $this->getViewer();
    $class = 'PhabricatorBadgesApplication';

    if (!PhabricatorApplication::isClassInstalledForViewer($class, $viewer)) {
      return null;
    }

    $awards = array();
    $badges = array();
    if ($user->getBadgePHIDs()) {
      $awards = id(new PhabricatorBadgesAwardQuery())
        ->setViewer($viewer)
        ->withRecipientPHIDs(array($user->getPHID()))
        ->execute();
      $awards = mpull($awards, null, 'getBadgePHID');

      $badges = array();
      foreach ($awards as $award) {
        $badge = $award->getBadge();
        if ($badge->getStatus() == PhabricatorBadgesBadge::STATUS_ACTIVE) {
          $badges[$award->getBadgePHID()] = $badge;
        }
      }
    }

    if (count($badges)) {
      $flex = new PHUIBadgeBoxView();

      foreach ($badges as $badge) {
        if ($badge) {
          $awarder_info = array();

          $award = idx($awards, $badge->getPHID(), null);
          $awarder_phid = $award->getAwarderPHID();
          $awarder_handle = $viewer->renderHandle($awarder_phid);

          $awarder_info = pht(
            'Awarded by %s',
            $awarder_handle->render());

          $item = id(new PHUIBadgeView())
            ->setIcon($badge->getIcon())
            ->setHeader($badge->getName())
            ->setSubhead($badge->getFlavor())
            ->setQuality($badge->getQuality())
            ->setHref($badge->getViewURI())
            ->addByLine($awarder_info);

          $flex->addItem($item);
        }
      }
    } else {
      $error = id(new PHUIBoxView())
        ->addClass('mlb')
        ->appendChild(pht('User does not have any badges.'));
      $flex = id(new PHUIInfoView())
        ->setSeverity(PHUIInfoView::SEVERITY_NODATA)
        ->appendChild($error);
    }

    // Best option?
    $badges = id(new PhabricatorBadgesQuery())
      ->setViewer($viewer)
      ->withStatuses(array(
        PhabricatorBadgesBadge::STATUS_ACTIVE,
      ))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->execute();

    $button = id(new PHUIButtonView())
      ->setTag('a')
      ->setIcon('fa-plus')
      ->setText(pht('Award'))
      ->setWorkflow(true)
      ->setHref('/badges/award/'.$user->getID().'/');

    $can_award = false;
    if (count($badges)) {
      $can_award = true;
    }

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Badges'));

    if (count($badges)) {
      $header->addActionLink($button);
    }

    $box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->addClass('project-view-badges')
      ->appendChild($flex)
      ->setBackground(PHUIObjectBoxView::GREY);

    return $box;
  }

  private function buildPeopleFeed(
    PhabricatorUser $user,
    $viewer) {

    $query = new PhabricatorFeedQuery();
    $query->setFilterPHIDs(
      array(
        $user->getPHID(),
      ));
    $query->setLimit(100);
    $query->setViewer($viewer);
    $stories = $query->execute();

    $builder = new PhabricatorFeedBuilder($stories);
    $builder->setUser($viewer);
    $builder->setShowHovercards(true);
    $builder->setNoDataString(pht('To begin on such a grand journey, '.
      'requires but just a single step.'));
    $view = $builder->buildView();

    return $view->render();

  }

}
