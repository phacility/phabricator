<?php

final class PhabricatorRepositoriesApplication extends PhabricatorApplication {

  public function getBaseURI() {
    return '/repository/';
  }

  public function getFontIcon() {
    return 'fa-hdd-o';
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
      ),
    );
  }

}
