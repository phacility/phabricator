<?php

final class PhabricatorAlmanacApplication extends PhabricatorApplication {

  public function getBaseURI() {
    return '/almanac/';
  }

  public function getName() {
    return pht('Almanac');
  }

  public function getShortDescription() {
    return pht('Service Directory');
  }

  public function getFontIcon() {
    return 'fa-server';
  }

  public function getTitleGlyph() {
    return "\xE2\x98\x82";
  }

  public function getApplicationGroup() {
    return self::GROUP_UTILITIES;
  }

  public function getHelpDocumentationArticles(PhabricatorUser $viewer) {
    return array(
      array(
        'name' => pht('Alamanac User Guide'),
        'href' => PhabricatorEnv::getDoclink('Almanac User Guide'),
      ),
    );
  }

  public function isPrototype() {
    return true;
  }

  public function getRoutes() {
    return array(
      '/almanac/' => array(
        '' => 'AlmanacConsoleController',
        'service/' => array(
          '(?:query/(?P<queryKey>[^/]+)/)?' => 'AlmanacServiceListController',
          'edit/(?:(?P<id>\d+)/)?' => 'AlmanacServiceEditController',
          'view/(?P<name>[^/]+)/' => 'AlmanacServiceViewController',
        ),
        'device/' => array(
          '(?:query/(?P<queryKey>[^/]+)/)?' => 'AlmanacDeviceListController',
          'edit/(?:(?P<id>\d+)/)?' => 'AlmanacDeviceEditController',
          'view/(?P<name>[^/]+)/' => 'AlmanacDeviceViewController',
        ),
        'interface/' => array(
          'edit/(?:(?P<id>\d+)/)?' => 'AlmanacInterfaceEditController',
        ),
        'binding/' => array(
          'edit/(?:(?P<id>\d+)/)?' => 'AlmanacBindingEditController',
          '(?P<id>\d+)/' => 'AlmanacBindingViewController',
        ),
        'network/' => array(
          '(?:query/(?P<queryKey>[^/]+)/)?' => 'AlmanacNetworkListController',
          'edit/(?:(?P<id>\d+)/)?' => 'AlmanacNetworkEditController',
          '(?P<id>\d+)/' => 'AlmanacNetworkViewController',
        ),
        'property/' => array(
          'edit/' => 'AlmanacPropertyEditController',
          'delete/' => 'AlmanacPropertyDeleteController',
        ),
      ),
    );
  }

  protected function getCustomCapabilities() {
    return array(
      AlmanacCreateServicesCapability::CAPABILITY => array(
        'default' => PhabricatorPolicies::POLICY_ADMIN,
      ),
      AlmanacCreateDevicesCapability::CAPABILITY => array(
        'default' => PhabricatorPolicies::POLICY_ADMIN,
      ),
      AlmanacCreateNetworksCapability::CAPABILITY => array(
        'default' => PhabricatorPolicies::POLICY_ADMIN,
      ),
      AlmanacCreateClusterServicesCapability::CAPABILITY => array(
        'default' => PhabricatorPolicies::POLICY_ADMIN,
      ),
    );
  }

}
