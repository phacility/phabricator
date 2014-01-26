<?php

final class PhabricatorApplicationHome extends PhabricatorApplication {

  public function getBaseURI() {
    return '/';
  }

  public function getShortDescription() {
    return pht('Where the Heart Is');
  }

  public function getIconName() {
    return 'home';
  }

  public function getRoutes() {
    return array(
      '/(?:(?P<filter>(?:jump))/)?' => 'PhabricatorHomeMainController',
    );
  }

  public function shouldAppearInLaunchView() {
    return false;
  }

  public function canUninstall() {
    return false;
  }

}
