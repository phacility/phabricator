<?php

final class PhabricatorPhurlApplication extends PhabricatorApplication {

  public function getName() {
    return pht('Phurl');
  }

  public function getShortDescription() {
    return pht('URL Shortener');
  }

  public function getFlavorText() {
    return pht('Shorten your favorite URL.');
  }

  public function getBaseURI() {
    return '/phurl/';
  }

  public function getIcon() {
    return 'fa-compress';
  }

  public function isPrototype() {
    return true;
  }

  public function getApplicationGroup() {
    return self::GROUP_UTILITIES;
  }

  public function getRemarkupRules() {
    return array(
      new PhabricatorPhurlRemarkupRule(),
      new PhabricatorPhurlLinkRemarkupRule(),
    );
  }

  public function getRoutes() {
    return array(
      '/U(?P<id>[1-9]\d*)/?' => 'PhabricatorPhurlURLViewController',
      '/u/(?P<id>[1-9]\d*)/?' => 'PhabricatorPhurlURLAccessController',
      '/u/(?P<alias>[^/]+)/?' => 'PhabricatorPhurlURLAccessController',
      '/phurl/' => array(
        '(?:query/(?P<queryKey>[^/]+)/)?'
          => 'PhabricatorPhurlURLListController',
        'url/' => array(
          $this->getEditRoutePattern('edit/')
            => 'PhabricatorPhurlURLEditController',
        ),
      ),
    );
  }

  public function getShortRoutes() {
    return array(
      '/status/' => 'PhabricatorStatusController',
      '/favicon.ico' => 'PhabricatorFaviconController',
      '/robots.txt' => 'PhabricatorRobotsShortController',

      '/u/(?P<append>[^/]+)' => 'PhabricatorPhurlShortURLController',
      '.*' => 'PhabricatorPhurlShortURLDefaultController',
    );
  }

  protected function getCustomCapabilities() {
    return array(
      PhabricatorPhurlURLCreateCapability::CAPABILITY => array(
        'default' => PhabricatorPolicies::POLICY_USER,
      ),
    );
  }

}
