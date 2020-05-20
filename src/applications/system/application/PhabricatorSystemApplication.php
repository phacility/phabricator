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

  public function getEventListeners() {
    return array(
      new PhabricatorSystemDebugUIEventListener(),
    );
  }

  public function getRoutes() {
    return array(
      '/status/' => 'PhabricatorStatusController',
      '/debug/' => 'PhabricatorDebugController',
      '/favicon.ico' => 'PhabricatorFaviconController',
      '/robots.txt' => 'PhabricatorRobotsController',
      '/services/' => array(
        'encoding/' => 'PhabricatorSystemSelectEncodingController',
        'highlight/' => 'PhabricatorSystemSelectHighlightController',
        'viewas/' => 'PhabricatorSystemSelectViewAsController',
      ),
      '/readonly/' => array(
        '(?P<reason>[^/]+)/' => 'PhabricatorSystemReadOnlyController',
      ),
      '/object/(?P<name>[^/]+)/' => 'PhabricatorSystemObjectController',
    );
  }

}
