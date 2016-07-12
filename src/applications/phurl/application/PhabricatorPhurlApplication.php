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
          'create/'
            => 'PhabricatorPhurlURLEditController',
          'edit/(?P<id>[1-9]\d*)/'
            => 'PhabricatorPhurlURLEditController',
          'comment/(?P<id>[1-9]\d*)/'
            => 'PhabricatorPhurlURLCommentController',
        ),
      ),
    );
  }

  public function getShortRoutes() {
    return array(
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
