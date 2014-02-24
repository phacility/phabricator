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
        'logs/' => 'PhabricatorPeopleLogsController',
        'approve/(?P<id>[1-9]\d*)/' => 'PhabricatorPeopleApproveController',
        'disable/(?P<id>[1-9]\d*)/' => 'PhabricatorPeopleDisableController',
        'edit/(?:(?P<id>[1-9]\d*)/(?:(?P<view>\w+)/)?)?'
          => 'PhabricatorPeopleEditController',
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

}
