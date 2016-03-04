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
    require_celerity_resource('project-view-css');

    $home = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setFluid(true)
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

  private function buildBadgesView(PhabricatorUser $user) {

    $viewer = $this->getViewer();
    $class = 'PhabricatorBadgesApplication';

    if (!PhabricatorApplication::isClassInstalledForViewer($class, $viewer)) {
      return null;
    }

    $badge_phids = $user->getBadgePHIDs();
    if ($badge_phids) {
      $badges = id(new PhabricatorBadgesQuery())
        ->setViewer($viewer)
        ->withPHIDs($badge_phids)
        ->withStatuses(array(PhabricatorBadgesBadge::STATUS_ACTIVE))
        ->execute();

      $flex = new PHUIBadgeBoxView();
      foreach ($badges as $badge) {
        $item = id(new PHUIBadgeView())
          ->setIcon($badge->getIcon())
          ->setHeader($badge->getName())
          ->setSubhead($badge->getFlavor())
          ->setQuality($badge->getQuality());
        $flex->addItem($item);
      }

    } else {
      $error = id(new PHUIBoxView())
        ->addClass('mlb')
        ->appendChild(pht('User does not have any badges.'));
      $flex = id(new PHUIInfoView())
        ->setSeverity(PHUIInfoView::SEVERITY_NODATA)
        ->appendChild($error);
    }

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Badges'))
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
