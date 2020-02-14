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
        $this->getQueryRoutePattern() => 'PhabricatorPeopleListController',
        'logs/' => array(
          $this->getQueryRoutePattern() => 'PhabricatorPeopleLogsController',
          '(?P<id>\d+)/' => 'PhabricatorPeopleLogViewController',
        ),
        'invite/' => array(
          '(?:query/(?P<queryKey>[^/]+)/)?'
            => 'PhabricatorPeopleInviteListController',
          'send/'
            => 'PhabricatorPeopleInviteSendController',
        ),
        'approve/(?P<id>[1-9]\d*)/(?:via/(?P<via>[^/]+)/)?'
          => 'PhabricatorPeopleApproveController',
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
        'editprofile/(?P<id>[1-9]\d*)/' =>
          'PhabricatorPeopleProfileEditController',
        'badges/(?P<id>[1-9]\d*)/' =>
          'PhabricatorPeopleProfileBadgesController',
        'tasks/(?P<id>[1-9]\d*)/' =>
          'PhabricatorPeopleProfileTasksController',
        'commits/(?P<id>[1-9]\d*)/' =>
          'PhabricatorPeopleProfileCommitsController',
        'revisions/(?P<id>[1-9]\d*)/' =>
          'PhabricatorPeopleProfileRevisionsController',
        'picture/(?P<id>[1-9]\d*)/' =>
          'PhabricatorPeopleProfilePictureController',
        'manage/(?P<id>[1-9]\d*)/' =>
          'PhabricatorPeopleProfileManageController',
      ),
      '/p/(?P<username>[\w._-]+)/' => array(
        '' => 'PhabricatorPeopleProfileViewController',
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
      PeopleDisableUsersCapability::CAPABILITY => array(
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
