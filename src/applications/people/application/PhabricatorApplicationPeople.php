<?php

final class PhabricatorApplicationPeople extends PhabricatorApplication {

  public function getShortDescription() {
    return pht('User Accounts');
  }

  public function getBaseURI() {
    return '/people/';
  }

  public function getTitleGlyph() {
    return "\xE2\x99\x9F";
  }

  public function getIconName() {
    return 'people';
  }

  public function getFlavorText() {
    return pht('Sort of a social utility.');
  }

  public function getApplicationGroup() {
    return self::GROUP_ORGANIZATION;
  }

  public function canUninstall() {
    return false;
  }

  public function getEventListeners() {
    return array(
      new PhabricatorPeopleHovercardEventListener(),
    );
  }

  public function getRoutes() {
    return array(
      '/people/' => array(
        '(query/(?P<key>[^/]+)/)?' => 'PhabricatorPeopleListController',
        'logs/(?:query/(?P<queryKey>[^/]+)/)?'
          => 'PhabricatorPeopleLogsController',
        'approve/(?P<id>[1-9]\d*)/' => 'PhabricatorPeopleApproveController',
        '(?P<via>disapprove)/(?P<id>[1-9]\d*)/'
          => 'PhabricatorPeopleDisableController',
        '(?P<via>disable)/(?P<id>[1-9]\d*)/'
          => 'PhabricatorPeopleDisableController',
        'empower/(?P<id>[1-9]\d*)/' => 'PhabricatorPeopleEmpowerController',
        'delete/(?P<id>[1-9]\d*)/' => 'PhabricatorPeopleDeleteController',
        'rename/(?P<id>[1-9]\d*)/' => 'PhabricatorPeopleRenameController',
        'welcome/(?P<id>[1-9]\d*)/' => 'PhabricatorPeopleWelcomeController',
        'create/' => 'PhabricatorPeopleCreateController',
        'new/(?P<type>[^/]+)/' => 'PhabricatorPeopleNewController',
        'ldap/' => 'PhabricatorPeopleLdapController',
        'editprofile/(?P<id>[1-9]\d*)/' =>
          'PhabricatorPeopleProfileEditController',
        'picture/(?P<id>[1-9]\d*)/' =>
          'PhabricatorPeopleProfilePictureController',
      ),
      '/p/(?P<username>[\w._-]+)/'
        => 'PhabricatorPeopleProfileController',
      '/p/(?P<username>[\w._-]+)/calendar/'
        => 'PhabricatorPeopleCalendarController',
    );
  }

  public function getRemarkupRules() {
    return array(
      new PhabricatorRemarkupRuleMention(),
    );
  }


  protected function getCustomCapabilities() {
    return array(
      PeopleCapabilityBrowseUserDirectory::CAPABILITY => array(
      ),
    );
  }

  public function loadStatus(PhabricatorUser $user) {
    if (!$user->getIsAdmin()) {
      return array();
    }

    $need_approval = id(new PhabricatorPeopleQuery())
      ->setViewer($user)
      ->withIsApproved(false)
      ->withIsDisabled(false)
      ->execute();

    if (!$need_approval) {
      return array();
    }

    $status = array();

    $count = count($need_approval);
    $type = PhabricatorApplicationStatusView::TYPE_NEEDS_ATTENTION;
    $status[] = id(new PhabricatorApplicationStatusView())
      ->setType($type)
      ->setText(pht('%d User(s) Need Approval', $count))
      ->setCount($count);

    return $status;
  }

  public function buildMainMenuItems(
    PhabricatorUser $user,
    PhabricatorController $controller = null) {

    $items = array();

    if ($user->isLoggedIn() && $user->isUserActivated()) {
      $image = $user->loadProfileImageURI();

      $item = id(new PHUIListItemView())
        ->setName($user->getUsername())
        ->setHref('/p/'.$user->getUsername().'/')
        ->addClass('core-menu-item')
        ->setAural(pht('Profile'))
        ->setOrder(100);

      $classes = array(
        'phabricator-core-menu-icon',
        'phabricator-core-menu-profile-image',
      );

      $item->appendChild(
        phutil_tag(
          'span',
          array(
            'class' => implode(' ', $classes),
            'style' => 'background-image: url('.$image.')',
          ),
          ''));

      $items[] = $item;
    }

    return $items;
  }


  public function getQuickCreateItems(PhabricatorUser $viewer) {
    $items = array();

    if ($viewer->getIsAdmin()) {
      $item = id(new PHUIListItemView())
        ->setName(pht('User Account'))
        ->setAppIcon('people-dark')
        ->setHref($this->getBaseURI().'create/');
      $items[] = $item;
    }

    return $items;
  }


}
