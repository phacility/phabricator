<?php

final class PhabricatorApplicationProject extends PhabricatorApplication {

  public function getName() {
    return pht('Projects');
  }

  public function getShortDescription() {
    return pht('Organize Work');
  }

  public function getBaseURI() {
    return '/project/';
  }

  public function getIconName() {
    return 'projects';
  }

  public function getFlavorText() {
    return pht('Group stuff into big piles.');
  }

  public function getApplicationGroup() {
    return self::GROUP_ORGANIZATION;
  }

  public function getRemarkupRules() {
    return array(
      new ProjectRemarkupRule(),
    );
  }

  public function getRoutes() {
    return array(
      '/project/' => array(
        '(?:query/(?P<queryKey>[^/]+)/)?' => 'PhabricatorProjectListController',
        'filter/(?P<filter>[^/]+)/' => 'PhabricatorProjectListController',
        'edit/(?P<id>[1-9]\d*)/' => 'PhabricatorProjectEditMainController',
        'details/(?P<id>[1-9]\d*)/'
          => 'PhabricatorProjectEditDetailsController',
        'archive/(?P<id>[1-9]\d*)/' =>
          'PhabricatorProjectArchiveController',
        'members/(?P<id>[1-9]\d*)/'
          => 'PhabricatorProjectMembersEditController',
        'members/(?P<id>[1-9]\d*)/remove/'
          => 'PhabricatorProjectMembersRemoveController',
        'view/(?P<id>[1-9]\d*)/'
          => 'PhabricatorProjectProfileController',
        'picture/(?P<id>[1-9]\d*)/' =>
          'PhabricatorProjectEditPictureController',
        'create/' => 'PhabricatorProjectCreateController',
        'board/(?P<id>[1-9]\d*)/' => 'PhabricatorProjectBoardViewController',
        'move/(?P<id>[1-9]\d*)/' => 'PhabricatorProjectMoveController',
        'board/(?P<projectID>[1-9]\d*)/edit/(?:(?P<id>\d+)/)?'
          => 'PhabricatorProjectBoardEditController',
        'board/(?P<projectID>[1-9]\d*)/delete/(?:(?P<id>\d+)/)?'
          => 'PhabricatorProjectBoardDeleteController',
        'board/(?P<projectID>[1-9]\d*)/column/(?:(?P<id>\d+)/)?'
          => 'PhabricatorProjectColumnDetailController',
        'update/(?P<id>[1-9]\d*)/(?P<action>[^/]+)/'
          => 'PhabricatorProjectUpdateController',
        'history/(?P<id>[1-9]\d*)/' => 'PhabricatorProjectHistoryController',
      ),
    );
  }

  protected function getCustomCapabilities() {
    return array(
      ProjectCapabilityCreateProjects::CAPABILITY => array(
      ),
    );
  }

}
