<?php

final class PhabricatorApplicationSystem extends PhabricatorApplication {

  public function canUninstall() {
    return false;
  }

  public function isUnlisted() {
    return true;
  }

  public function getRoutes() {
    return array(
      '/status/' => 'PhabricatorStatusController',
      '/debug/' => 'PhabricatorDebugController',
      '/robots.txt' => 'PhabricatorRobotsController',
    );
  }

}
