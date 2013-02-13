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

  public function getRoutes() {
    return array(
      '/project/' => array(
        '' => 'PhabricatorProjectListController',
        'filter/(?P<filter>[^/]+)/' => 'PhabricatorProjectListController',
        'edit/(?P<id>[1-9]\d*)/' => 'PhabricatorProjectProfileEditController',
        'members/(?P<id>[1-9]\d*)/'
          => 'PhabricatorProjectMembersEditController',
        'view/(?P<id>[1-9]\d*)/(?:(?P<page>\w+)/)?'
          => 'PhabricatorProjectProfileController',
        'create/' => 'PhabricatorProjectCreateController',
        'update/(?P<id>[1-9]\d*)/(?P<action>[^/]+)/'
          => 'PhabricatorProjectUpdateController',
      ),
    );
  }

}
