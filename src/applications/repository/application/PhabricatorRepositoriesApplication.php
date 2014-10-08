<?php

final class PhabricatorRepositoriesApplication extends PhabricatorApplication {

  public function getBaseURI() {
    return '/repository/';
  }

  public function getIconName() {
    return 'repositories';
  }

  public function getName() {
    return pht('Repositories');
  }

  public function getShortDescription() {
    return pht('(Deprecated)');
  }

  public function getTitleGlyph() {
    return 'rX';
  }

  public function getApplicationGroup() {
    return self::GROUP_ADMIN;
  }

  public function getRoutes() {
    return array(
      '/repository/' => array(
        '' => 'PhabricatorRepositoryListController',
        'project/edit/(?P<id>[1-9]\d*)/'
          => 'PhabricatorRepositoryArcanistProjectEditController',
        'project/delete/(?P<id>[1-9]\d*)/'
          => 'PhabricatorRepositoryArcanistProjectDeleteController',
      ),
    );
  }

}
