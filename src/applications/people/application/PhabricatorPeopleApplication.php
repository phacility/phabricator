<?php

final class PhabricatorPeopleApplication extends PhabricatorApplication {

  public function getName() {
    return pht('People');
  }

  public function getShortDescription() {
    return pht('User Accounts and Profiles');
  }

  public function getBaseURI() {
    return '/people/';
  }

  public function getTitleGlyph() {
    return "\xE2\x99\x9F";
  }

  public function getIcon() {
    return 'fa-users';
  }

  public function isPinnedByDefault(PhabricatorUser $viewer) {
    return $viewer->getIsAdmin();
  }

  public function getFlavorText() {
    return pht('Sort of a social utility.');
  }

  public function canUninstall() {
    return false;
  }

  public function getRoutes() {
    return array(
      '/people/' => array(
        '(query/(?P<key>[^/]+)/)?' => 'PhabricatorPeopleListController',
        'logs/(?:query/(?P<queryKey>[^/]+)/)?'
          => 'PhabricatorPeopleLogsController',
        'invite/' => array(
          '(?:query/(?P<queryKey>[^/]+)/)?'
            => 'PhabricatorPeopleInviteListController',
          'send/'
            => 'PhabricatorPeopleInviteSendController',
        ),
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
        'manage/(?P<id>[1-9]\d*)/' =>
          'PhabricatorPeopleProfileManageController',
        ),
      '/p/(?P<username>[\w._-]+)/' => array(
        '' => 'PhabricatorPeopleProfileViewController',
        'panel/'
          => $this->getPanelRouting('PhabricatorPeopleProfilePanelController'),
        'calendar/' => 'PhabricatorPeopleCalendarController',
      ),
    );
  }

  public function getRemarkupRules() {
    return array(
      new PhabricatorMentionRemarkupRule(),
    );
  }

  protected function getCustomCapabilities() {
    return array(
      PeopleCreateUsersCapability::CAPABILITY => array(
        'default' => PhabricatorPolicies::POLICY_ADMIN,
      ),
      PeopleBrowseUserDirectoryCapability::CAPABILITY => array(),
    );
  }

  public function loadStatus(PhabricatorUser $user) {
    if (!$user->getIsAdmin()) {
      return array();
    }
    $limit = self::MAX_STATUS_ITEMS;

    $need_approval = id(new PhabricatorPeopleQuery())
      ->setViewer($user)
      ->withIsApproved(false)
      ->withIsDisabled(false)
      ->setLimit($limit)
      ->execute();
    if (!$need_approval) {
      return array();
    }

    $status = array();

    $count = count($need_approval);
    if ($count >= $limit) {
      $count_str = pht(
        '%s+ User(s) Need Approval',
        new PhutilNumber($limit - 1));
    } else {
      $count_str = pht(
        '%s User(s) Need Approval',
        new PhutilNumber($count));
    }

    $type = PhabricatorApplicationStatusView::TYPE_NEEDS_ATTENTION;
    $status[] = id(new PhabricatorApplicationStatusView())
      ->setType($type)
      ->setText($count_str)
      ->setCount($count);

    return $status;
  }

  public function getApplicationSearchDocumentTypes() {
    return array(
      PhabricatorPeopleUserPHIDType::TYPECONST,
    );
  }

}
