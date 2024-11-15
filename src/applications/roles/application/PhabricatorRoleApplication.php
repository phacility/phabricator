<?php

final class PhabricatorRoleApplication extends PhabricatorApplication {

  public function getName() {
    return pht('Roles');
  }

  public function getShortDescription() {
    return pht('Roles, Tags, and Teams');
  }

  public function isPinnedByDefault(PhabricatorUser $viewer) {
    return true;
  }

  public function getBaseURI() {
    return '/role/';
  }

  public function getIcon() {
    return 'fa-briefcase';
  }

  public function getFlavorText() {
    return pht('Group stuff into big piles.');
  }

  public function getRemarkupRules() {
    return array(
      new RoleRemarkupRule(),
    );
  }

  public function getEventListeners() {
    return array(
      new PhabricatorRoleUIEventListener(),
    );
  }

  public function getRoutes() {
    return array(
      '/role/' => array(
        '(?:query/(?P<queryKey>[^/]+)/)?' => 'PhabricatorRoleListController',
        'filter/(?P<filter>[^/]+)/' => 'PhabricatorRoleListController',
        'archive/(?P<id>[1-9]\d*)/'
          => 'PhabricatorRoleArchiveController',
        'lock/(?P<id>[1-9]\d*)/'
          => 'PhabricatorRoleLockController',
        'members/(?P<id>[1-9]\d*)/'
          => 'PhabricatorRoleMembersViewController',
        'members/(?P<id>[1-9]\d*)/add/'
          => 'PhabricatorRoleMembersAddController',
        '(?P<type>members|watchers)/(?P<id>[1-9]\d*)/remove/'
          => 'PhabricatorRoleMembersRemoveController',
        'profile/(?P<id>[1-9]\d*)/'
          => 'PhabricatorRoleProfileController',
        'view/(?P<id>[1-9]\d*)/'
          => 'PhabricatorRoleViewController',
        'picture/(?P<id>[1-9]\d*)/'
          => 'PhabricatorRoleEditPictureController',
        $this->getEditRoutePattern('edit/')
          => 'PhabricatorRoleEditController',
        '(?P<roleID>[1-9]\d*)/item/' => $this->getProfileMenuRouting(
          'PhabricatorRoleMenuItemController'),
        'subroles/(?P<id>[1-9]\d*)/'
          => 'PhabricatorRoleSubrolesController',
        'board/(?P<id>[1-9]\d*)/'.
          '(?:query/(?P<queryKey>[^/]+)/)?'
          => 'PhabricatorRoleBoardViewController',
        'move/(?P<id>[1-9]\d*)/' => 'PhabricatorRoleMoveController',
        'cover/' => 'PhabricatorRoleCoverController',
        'reports/(?P<roleID>[1-9]\d*)/' =>
          'PhabricatorRoleReportsController',
        'board/(?P<roleID>[1-9]\d*)/' => array(
          'edit/(?:(?P<id>\d+)/)?'
            => 'PhabricatorRoleColumnEditController',
          'hide/(?:(?P<id>\d+)/)?'
            => 'PhabricatorRoleColumnHideController',
          'column/(?:(?P<id>\d+)/)?'
            => 'PhabricatorRoleColumnDetailController',
          'viewquery/(?P<columnID>\d+)/'
            => 'PhabricatorRoleColumnViewQueryController',
          'bulk/(?P<columnID>\d+)/'
            => 'PhabricatorRoleColumnBulkEditController',
          'bulkmove/(?P<columnID>\d+)/(?P<mode>role|column)/'
            => 'PhabricatorRoleColumnBulkMoveController',
          'import/'
            => 'PhabricatorRoleBoardImportController',
          'reorder/'
            => 'PhabricatorRoleBoardReorderController',
          'disable/'
            => 'PhabricatorRoleBoardDisableController',
          'manage/'
            => 'PhabricatorRoleBoardManageController',
          'background/'
            => 'PhabricatorRoleBoardBackgroundController',
          'default/(?P<target>[^/]+)/'
            => 'PhabricatorRoleBoardDefaultController',
          'filter/(?:query/(?P<queryKey>[^/]+)/)?'
            => 'PhabricatorRoleBoardFilterController',
          'reload/'
            => 'PhabricatorRoleBoardReloadController',
        ),
        'column/' => array(
          'remove/(?P<id>\d+)/' =>
            'PhabricatorRoleColumnRemoveTriggerController',
        ),
        'trigger/' => array(
          $this->getQueryRoutePattern() =>
            'PhabricatorRoleTriggerListController',
          '(?P<id>[1-9]\d*)/' =>
            'PhabricatorRoleTriggerViewController',
          $this->getEditRoutePattern('edit/') =>
            'PhabricatorRoleTriggerEditController',
        ),
        'update/(?P<id>[1-9]\d*)/(?P<action>[^/]+)/'
          => 'PhabricatorRoleUpdateController',
        'manage/(?P<id>[1-9]\d*)/' => 'PhabricatorRoleManageController',
        '(?P<action>watch|unwatch)/(?P<id>[1-9]\d*)/'
          => 'PhabricatorRoleWatchController',
        'silence/(?P<id>[1-9]\d*)/'
          => 'PhabricatorRoleSilenceController',
        'warning/(?P<id>[1-9]\d*)/'
          => 'PhabricatorRoleSubroleWarningController',
      ),
    );
  }

  protected function getCustomCapabilities() {
    return array(
      RoleCreateRolesCapability::CAPABILITY => array(),
      RoleCanLockRolesCapability::CAPABILITY => array(
        'default' => PhabricatorPolicies::POLICY_ADMIN,
      ),
      RoleDefaultViewCapability::CAPABILITY => array(
        'caption' => pht('Default view policy for newly created roles.'),
        'template' => PhabricatorRoleRolePHIDType::TYPECONST,
        'capability' => PhabricatorPolicyCapability::CAN_VIEW,
      ),
      RoleDefaultEditCapability::CAPABILITY => array(
        'caption' => pht('Default edit policy for newly created roles.'),
        'template' => PhabricatorRoleRolePHIDType::TYPECONST,
        'capability' => PhabricatorPolicyCapability::CAN_EDIT,
      ),
      RoleDefaultJoinCapability::CAPABILITY => array(
        'caption' => pht('Default join policy for newly created roles.'),
        'template' => PhabricatorRoleRolePHIDType::TYPECONST,
        'capability' => PhabricatorPolicyCapability::CAN_JOIN,
      ),
    );
  }

  public function getApplicationSearchDocumentTypes() {
    return array(
      PhabricatorRoleRolePHIDType::TYPECONST,
    );
  }

  public function getApplicationOrder() {
    return 0.150;
  }

  public function getHelpDocumentationArticles(PhabricatorUser $viewer) {
    return array(
      array(
        'name' => pht('Roles User Guide'),
        'href' => PhabricatorEnv::getDoclink('Roles User Guide'),
      ),
    );
  }

}
