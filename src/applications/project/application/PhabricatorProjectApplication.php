<?php

final class PhabricatorProjectApplication extends PhabricatorApplication {

  public function getName() {
    return pht('Projects');
  }

  public function getShortDescription() {
    return pht('Get Organized');
  }

  public function isPinnedByDefault(PhabricatorUser $viewer) {
    return true;
  }

  public function getBaseURI() {
    return '/project/';
  }

  public function getFontIcon() {
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
        'details/(?P<id>[1-9]\d*)/'
          => 'PhabricatorProjectEditDetailsController',
        'archive/(?P<id>[1-9]\d*)/'
          => 'PhabricatorProjectArchiveController',
        'members/(?P<id>[1-9]\d*)/'
          => 'PhabricatorProjectMembersEditController',
        'members/(?P<id>[1-9]\d*)/remove/'
          => 'PhabricatorProjectMembersRemoveController',
        'profile/(?P<id>[1-9]\d*)/'
          => 'PhabricatorProjectProfileController',
        'feed/(?P<id>[1-9]\d*)/'
          => 'PhabricatorProjectFeedController',
        'view/(?P<id>[1-9]\d*)/'
          => 'PhabricatorProjectViewController',
        'picture/(?P<id>[1-9]\d*)/'
          => 'PhabricatorProjectEditPictureController',
        'icon/(?P<id>[1-9]\d*)/'
          => 'PhabricatorProjectEditIconController',
        'icon/'
          => 'PhabricatorProjectEditIconController',
        'create/' => 'PhabricatorProjectEditDetailsController',
        'board/(?P<id>[1-9]\d*)/'.
          '(?P<filter>filter/)?'.
          '(?:query/(?P<queryKey>[^/]+)/)?'
          => 'PhabricatorProjectBoardViewController',
        'move/(?P<id>[1-9]\d*)/' => 'PhabricatorProjectMoveController',
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
        ),
        'update/(?P<id>[1-9]\d*)/(?P<action>[^/]+)/'
          => 'PhabricatorProjectUpdateController',
        'history/(?P<id>[1-9]\d*)/' => 'PhabricatorProjectHistoryController',
        '(?P<action>watch|unwatch)/(?P<id>[1-9]\d*)/'
          => 'PhabricatorProjectWatchController',
      ),
      '/tag/' => array(
        '(?P<slug>[^/]+)/' => 'PhabricatorProjectViewController',
        '(?P<slug>[^/]+)/board/' => 'PhabricatorProjectBoardViewController',
      ),
    );
  }

  public function getQuickCreateItems(PhabricatorUser $viewer) {
    $can_create = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $this,
      ProjectCreateProjectsCapability::CAPABILITY);

    $items = array();
    if ($can_create) {
      $item = id(new PHUIListItemView())
        ->setName(pht('Project'))
        ->setIcon('fa-briefcase')
        ->setHref($this->getBaseURI().'create/');
      $items[] = $item;
    }

    return $items;
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

}
