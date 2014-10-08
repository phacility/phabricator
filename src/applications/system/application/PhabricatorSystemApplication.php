<?php

final class PhabricatorSystemApplication extends PhabricatorApplication {

  public function getName() {
    return pht('System');
  }

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
      '/services/' => array(
        'encoding/' => 'PhabricatorSystemSelectEncodingController',
        'highlight/' => 'PhabricatorSystemSelectHighlightController',
      ),
    );
  }

}
