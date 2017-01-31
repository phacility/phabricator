<?php

final class PhabricatorHomeApplication extends PhabricatorApplication {

  const DASHBOARD_DEFAULT = 'dashboard:default';

  public function getBaseURI() {
    return '/home/';
  }

  public function getName() {
    return pht('Home');
  }

  public function getShortDescription() {
    return pht('Command Center');
  }

  public function getIcon() {
    return 'fa-home';
  }

  public function getRoutes() {
    return array(
      '/' => 'PhabricatorHomeMenuItemController',
      '/home/' => array(
        '' => 'PhabricatorHomeMenuItemController',
        'menu/' => $this->getProfileMenuRouting(
          'PhabricatorHomeMenuItemController'),
      ),
    );
  }

  public function isLaunchable() {
    return false;
  }

  public function getApplicationOrder() {
    return 9;
  }

}
