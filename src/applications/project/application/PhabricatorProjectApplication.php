<?php

final class PhabricatorProjectApplication extends PhabricatorApplication {

  public function getName() {
    return pht('Projects');
  }

  public function getShortDescription() {
    return pht('Projects, Tags, and Teams');
  }

  public function isPinnedByDefault(PhabricatorUser $viewer) {
    return true;
  }

  public function getBaseURI() {
    return '/project/';
  }

  public function getIcon() {
    return 'fa-briefcase';
  }

  public function getFlavorText() {
    return pht('Group stuff into big piles.');
  }

  public function getRemarkupRules() {
    return array(
      new ProjectRemarkupRule(),
    );
  }

  public function getEventListeners() {
    return array(
      new PhabricatorProjectUIEventListener(),
    );
  }

  public function getRoutes() {
    return array(
      '/project/' => array(
        '(?:query/(?P<queryKey>[^/]+)/)?' => 'PhabricatorProjectListController',
        'filter/(?P<filter>[^/]+)/' => 'PhabricatorProjectListController',
        'archive/(?P<id>[1-9]\d*)/'
          => 'PhabricatorProjectArchiveController',
        'lock/(?P<id>[1-9]\d*)/'
          => 'PhabricatorProjectLockController',
        'members/(?P<id>[1-9]\d*)/'
          => 'PhabricatorProjectMembersViewController',
        'members/(?P<id>[1-9]\d*)/add/'
          => 'PhabricatorProjectMembersAddController',
        '(?P<type>members|watchers)/(?P<id>[1-9]\d*)/remove/'
          => 'PhabricatorProjectMembersRemoveController',
        'profile/(?P<id>[1-9]\d*)/'
          => 'PhabricatorProjectProfileController',
        'view/(?P<id>[1-9]\d*)/'
          => 'PhabricatorProjectViewController',
        'picture/(?P<id>[1-9]\d*)/'
          => 'PhabricatorProjectEditPictureController',
        $this->getEditRoutePattern('edit/')
          => 'PhabricatorProjectEditController',
        '(?P<projectID>[1-9]\d*)/item/' => $this->getProfileMenuRouting(
          'PhabricatorProjectMenuItemController'),
        'subprojects/(?P<id>[1-9]\d*)/'
          => 'PhabricatorProjectSubprojectsController',
        'board/(?P<id>[1-9]\d*)/'.
          '(?P<filter>filter/)?'.
          '(?:query/(?P<queryKey>[^/]+)/)?'
          => 'PhabricatorProjectBoardViewController',
        'move/(?P<id>[1-9]\d*)/' => 'PhabricatorProjectMoveController',
        'cover/' => 'PhabricatorProjectCoverController',
        'board/(?P<projectID>[1-9]\d*)/' => array(
          'edit/(?:(?P<id>\d+)/)?'
            => 'PhabricatorProjectColumnEditController',
          'hide/(?:(?P<id>\d+)/)?'
            => 'PhabricatorProjectColumnHideController',
          'column/(?:(?P<id>\d+)/)?'
            => 'PhabricatorProjectColumnDetailController',
          'import/'
            => 'PhabricatorProjectBoardImportController',
          'reorder/'
            => 'PhabricatorProjectBoardReorderController',
          'disable/'
            => 'PhabricatorProjectBoardDisableController',
          'manage/'
            => 'PhabricatorProjectBoardManageController',
          'background/'
            => 'PhabricatorProjectBoardBackgroundController',
        ),
        'update/(?P<id>[1-9]\d*)/(?P<action>[^/]+)/'
          => 'PhabricatorProjectUpdateController',
        'manage/(?P<id>[1-9]\d*)/' => 'PhabricatorProjectManageController',
        '(?P<action>watch|unwatch)/(?P<id>[1-9]\d*)/'
          => 'PhabricatorProjectWatchController',
        'silence/(?P<id>[1-9]\d*)/'
          => 'PhabricatorProjectSilenceController',
        'warning/(?P<id>[1-9]\d*)/'
          => 'PhabricatorProjectSubprojectWarningController',
        'default/(?P<projectID>[1-9]\d*)/(?P<target>[^/]+)/'
          => 'PhabricatorProjectDefaultController',
      ),
      '/tag/' => array(
        '(?P<slug>[^/]+)/' => 'PhabricatorProjectViewController',
        '(?P<slug>[^/]+)/board/' => 'PhabricatorProjectBoardViewController',
      ),
    );
  }

  protected function getCustomCapabilities() {
    return array(
      ProjectCreateProjectsCapability::CAPABILITY => array(),
      ProjectCanLockProjectsCapability::CAPABILITY => array(
        'default' => PhabricatorPolicies::POLICY_ADMIN,
      ),
      ProjectDefaultViewCapability::CAPABILITY => array(
        'caption' => pht('Default view policy for newly created projects.'),
        'template' => PhabricatorProjectProjectPHIDType::TYPECONST,
        'capability' => PhabricatorPolicyCapability::CAN_VIEW,
      ),
      ProjectDefaultEditCapability::CAPABILITY => array(
        'caption' => pht('Default edit policy for newly created projects.'),
        'template' => PhabricatorProjectProjectPHIDType::TYPECONST,
        'capability' => PhabricatorPolicyCapability::CAN_EDIT,
      ),
      ProjectDefaultJoinCapability::CAPABILITY => array(
        'caption' => pht('Default join policy for newly created projects.'),
        'template' => PhabricatorProjectProjectPHIDType::TYPECONST,
        'capability' => PhabricatorPolicyCapability::CAN_JOIN,
      ),
    );
  }

  public function getApplicationSearchDocumentTypes() {
    return array(
      PhabricatorProjectProjectPHIDType::TYPECONST,
    );
  }

  public function getApplicationOrder() {
    return 0.150;
  }

  public function getHelpDocumentationArticles(PhabricatorUser $viewer) {
    return array(
      array(
        'name' => pht('Projects User Guide'),
        'href' => PhabricatorEnv::getDoclink('Projects User Guide'),
      ),
    );
  }

}
