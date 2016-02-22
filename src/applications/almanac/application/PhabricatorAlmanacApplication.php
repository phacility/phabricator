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

  public function getIcon() {
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
        'name' => pht('Almanac User Guide'),
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
        '(?P<objectType>service)/' => array(
          $this->getQueryRoutePattern() => 'AlmanacServiceListController',
          'edit/(?:(?P<id>\d+)/)?' => 'AlmanacServiceEditController',
          'view/(?P<name>[^/]+)/' => 'AlmanacServiceViewController',
        ),
        '(?P<objectType>device)/' => array(
          $this->getQueryRoutePattern() => 'AlmanacDeviceListController',
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
          $this->getQueryRoutePattern()  => 'AlmanacNetworkListController',
          'edit/(?:(?P<id>\d+)/)?' => 'AlmanacNetworkEditController',
          '(?P<id>\d+)/' => 'AlmanacNetworkViewController',
        ),
        'namespace/' => array(
          $this->getQueryRoutePattern() => 'AlmanacNamespaceListController',
          $this->getEditRoutePattern('edit/')
            => 'AlmanacNamespaceEditController',
          '(?P<id>\d+)/' => 'AlmanacNamespaceViewController',
        ),
        'property/' => array(
          'delete/' => 'AlmanacPropertyDeleteController',
          'update/' => 'AlmanacPropertyEditController',
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
      AlmanacCreateNamespacesCapability::CAPABILITY => array(
        'default' => PhabricatorPolicies::POLICY_ADMIN,
      ),
      AlmanacCreateClusterServicesCapability::CAPABILITY => array(
        'default' => PhabricatorPolicies::POLICY_ADMIN,
      ),
    );
  }

}
