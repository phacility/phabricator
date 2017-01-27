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

  public function getApplicationGroup() {
    return self::GROUP_UTILITIES;
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
        'item/' => $this->getProfileMenuRouting(
          'PhabricatorPeopleProfileMenuController'),
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

  public function getApplicationSearchDocumentTypes() {
    return array(
      PhabricatorPeopleUserPHIDType::TYPECONST,
    );
  }

}
