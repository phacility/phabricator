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
      ->setIconFont($profile_icon.' grey');
    $profile_title = $profile->getDisplayTitle();

    $header = id(new PHUIHeaderView())
      ->setHeader($user->getFullName())
      ->setSubheader(array($profile_icon, $profile_title))
      ->setImage($picture);

    $actions = id(new PhabricatorActionListView())
      ->setObject($user)
      ->setUser($viewer);

    $class = 'PhabricatorConpherenceApplication';
    if (PhabricatorApplication::isClassInstalledForViewer($class, $viewer)) {
      $href = id(new PhutilURI('/conpherence/new/'))
        ->setQueryParam('participant', $user->getPHID());

      $can_send = $viewer->isLoggedIn();

      $actions->addAction(
        id(new PhabricatorActionView())
          ->setIcon('fa-comments')
          ->setName(pht('Send Message'))
          ->setWorkflow(true)
          ->setDisabled(!$can_send)
          ->setHref($href));
    }


    $properties = $this->buildPropertyView($user, $actions);
    $name = $user->getUsername();

    $object_box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->addPropertyList($properties);

    $feed = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Recent Activity'))
      ->appendChild($this->buildPeopleFeed($user, $viewer));

    $badges = $this->buildBadgesView($user);

    $nav = $this->getProfileMenu();
    $nav->selectFilter(PhabricatorPeopleProfilePanelEngine::PANEL_PROFILE);

    $crumbs = $this->buildApplicationCrumbs();

    return $this->newPage()
      ->setTitle($user->getUsername())
      ->setNavigation($nav)
      ->setCrumbs($crumbs)
      ->appendChild(
        array(
          $object_box,
          $badges,
          $feed,
        ));
  }

  private function buildPropertyView(
    PhabricatorUser $user,
    PhabricatorActionListView $actions) {

    $viewer = $this->getRequest()->getUser();
    $view = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->setObject($user)
      ->setActionList($actions);

    $field_list = PhabricatorCustomField::getObjectFields(
      $user,
      PhabricatorCustomField::ROLE_VIEW);
    $field_list->appendFieldsToPropertyList($user, $viewer, $view);

    return $view;
  }

  private function buildBadgesView(
    PhabricatorUser $user) {

    $viewer = $this->getViewer();
    $class = 'PhabricatorBadgesApplication';
    $box = null;

    if (PhabricatorApplication::isClassInstalledForViewer($class, $viewer)) {
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

        $box = id(new PHUIObjectBoxView())
          ->setHeaderText(pht('Badges'))
          ->appendChild($flex);
      }
    }

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

    return phutil_tag_div('phabricator-project-feed', $view->render());

  }

}
