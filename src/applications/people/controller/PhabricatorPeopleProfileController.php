<?php

final class PhabricatorPeopleProfileController
  extends PhabricatorPeopleController {

  private $username;

  public function shouldAllowPublic() {
    return true;
  }

  public function shouldRequireAdmin() {
    return false;
  }

  public function willProcessRequest(array $data) {
    $this->username = idx($data, 'username');
  }

  public function processRequest() {
    $viewer = $this->getRequest()->getUser();

    $user = id(new PhabricatorPeopleQuery())
      ->setViewer($viewer)
      ->withUsernames(array($this->username))
      ->needBadges(true)
      ->needProfileImage(true)
      ->needAvailability(true)
      ->executeOne();
    if (!$user) {
      return new Aphront404Response();
    }

    $profile = $user->loadUserProfile();
    $username = phutil_escape_uri($user->getUserName());

    $picture = $user->getProfileImageURI();

    $header = id(new PHUIHeaderView())
      ->setHeader($user->getFullName())
      ->setSubheader($profile->getTitle())
      ->setImage($picture);

    $actions = id(new PhabricatorActionListView())
      ->setObject($user)
      ->setObjectURI($this->getRequest()->getRequestURI())
      ->setUser($viewer);

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $user,
      PhabricatorPolicyCapability::CAN_EDIT);

    $actions->addAction(
      id(new PhabricatorActionView())
        ->setIcon('fa-pencil')
        ->setName(pht('Edit Profile'))
        ->setHref($this->getApplicationURI('editprofile/'.$user->getID().'/'))
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit));

    $actions->addAction(
      id(new PhabricatorActionView())
        ->setIcon('fa-picture-o')
        ->setName(pht('Edit Profile Picture'))
        ->setHref($this->getApplicationURI('picture/'.$user->getID().'/'))
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit));

    $class = 'PhabricatorConpherenceApplication';
    if (PhabricatorApplication::isClassInstalledForViewer($class, $viewer)) {
      $href = id(new PhutilURI('/conpherence/new/'))
        ->setQueryParam('participant', $user->getPHID());

      $actions->addAction(
        id(new PhabricatorActionView())
          ->setIcon('fa-comments')
          ->setName(pht('Send Message'))
          ->setWorkflow(true)
          ->setHref($href));
    }

    if ($viewer->getIsAdmin()) {
      $actions->addAction(
        id(new PhabricatorActionView())
          ->setIcon('fa-wrench')
          ->setName(pht('Edit Settings'))
          ->setDisabled(!$can_edit)
          ->setWorkflow(!$can_edit)
          ->setHref('/settings/'.$user->getID().'/'));

      if ($user->getIsAdmin()) {
        $empower_icon = 'fa-arrow-circle-o-down';
        $empower_name = pht('Remove Administrator');
      } else {
        $empower_icon = 'fa-arrow-circle-o-up';
        $empower_name = pht('Make Administrator');
      }

      $actions->addAction(
        id(new PhabricatorActionView())
          ->setIcon($empower_icon)
          ->setName($empower_name)
          ->setDisabled(($user->getPHID() == $viewer->getPHID()))
          ->setWorkflow(true)
          ->setHref($this->getApplicationURI('empower/'.$user->getID().'/')));

      $actions->addAction(
        id(new PhabricatorActionView())
          ->setIcon('fa-tag')
          ->setName(pht('Change Username'))
          ->setWorkflow(true)
          ->setHref($this->getApplicationURI('rename/'.$user->getID().'/')));

      if ($user->getIsDisabled()) {
        $disable_icon = 'fa-check-circle-o';
        $disable_name = pht('Enable User');
      } else {
        $disable_icon = 'fa-ban';
        $disable_name = pht('Disable User');
      }

      $actions->addAction(
        id(new PhabricatorActionView())
          ->setIcon($disable_icon)
          ->setName($disable_name)
          ->setDisabled(($user->getPHID() == $viewer->getPHID()))
          ->setWorkflow(true)
          ->setHref($this->getApplicationURI('disable/'.$user->getID().'/')));

      $actions->addAction(
        id(new PhabricatorActionView())
          ->setIcon('fa-times')
          ->setName(pht('Delete User'))
          ->setDisabled(($user->getPHID() == $viewer->getPHID()))
          ->setWorkflow(true)
          ->setHref($this->getApplicationURI('delete/'.$user->getID().'/')));

      $can_welcome = $user->canEstablishWebSessions();

      $actions->addAction(
        id(new PhabricatorActionView())
          ->setIcon('fa-envelope')
          ->setName(pht('Send Welcome Email'))
          ->setWorkflow(true)
          ->setDisabled(!$can_welcome)
          ->setHref($this->getApplicationURI('welcome/'.$user->getID().'/')));
    }

    $properties = $this->buildPropertyView($user, $actions);
    $name = $user->getUsername();

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb($name);

    $object_box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->addPropertyList($properties);

    $feed = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Recent Activity'))
      ->appendChild($this->buildPeopleFeed($user, $viewer));

    $badges = $this->buildBadgesView($user);

    $nav = $this->buildIconNavView($user);
    $nav->selectFilter("{$name}/");
    $nav->appendChild($object_box);
    $nav->appendChild($badges);
    $nav->appendChild($feed);

    return $this->buildApplicationPage(
      $nav,
      array(
        'title' => $user->getUsername(),
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
