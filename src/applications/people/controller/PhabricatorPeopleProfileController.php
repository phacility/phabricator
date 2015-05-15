<?php

final class PhabricatorPeopleProfileController
  extends PhabricatorPeopleController {

  private $username;

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
      ->needProfileImage(true)
      ->needAvailability(true)
      ->executeOne();
    if (!$user) {
      return new Aphront404Response();
    }

    require_celerity_resource('phabricator-profile-css');

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

      $actions->addAction(
        id(new PhabricatorActionView())
          ->setIcon('fa-envelope')
          ->setName(pht('Send Welcome Email'))
          ->setWorkflow(true)
          ->setHref($this->getApplicationURI('welcome/'.$user->getID().'/')));
    }

    $properties = $this->buildPropertyView($user, $actions);
    $name = $user->getUsername();

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb($name);

    $class = 'PhabricatorConpherenceApplication';
    if (PhabricatorApplication::isClassInstalledForViewer($class, $viewer)) {
      $href = '/conpherence/new/?participant='.$user->getPHID();
      $image = id(new PHUIIconView())
          ->setIconFont('fa-comments');
      $button = id(new PHUIButtonView())
        ->setTag('a')
        ->setColor(PHUIButtonView::SIMPLE)
        ->setIcon($image)
        ->setHref($href)
        ->setText(pht('Send Message'))
        ->setWorkflow(true);
      $header->addActionLink($button);
    }

    $object_box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->addPropertyList($properties);

    $nav = $this->buildIconNavView($user);
    $nav->selectFilter("{$name}/");
    $nav->appendChild($object_box);

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

}
