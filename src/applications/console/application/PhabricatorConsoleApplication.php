<?php

final class PhabricatorConsoleApplication extends PhabricatorApplication {

  public function getName() {
    return pht('Console');
  }

  public function canUninstall() {
    return false;
  }

  public function isUnlisted() {
    return true;
  }

  public function getRoutes() {
    return array(
      '/~/' => array(
        '' => 'DarkConsoleController',
        'data/(?P<key>[^/]+)/' => 'DarkConsoleDataController',
      ),
    );
  }

}
