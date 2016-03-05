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

  public function getRoutes() {
    return array(
      '/almanac/' => array(
        '' => 'AlmanacConsoleController',
        '(?P<objectType>service)/' => array(
          $this->getQueryRoutePattern() => 'AlmanacServiceListController',
          $this->getEditRoutePattern('edit/') => 'AlmanacServiceEditController',
          'view/(?P<name>[^/]+)/' => 'AlmanacServiceViewController',
        ),
        '(?P<objectType>device)/' => array(
          $this->getQueryRoutePattern() => 'AlmanacDeviceListController',
          $this->getEditRoutePattern('edit/') => 'AlmanacDeviceEditController',
          'view/(?P<name>[^/]+)/' => 'AlmanacDeviceViewController',
        ),
        'interface/' => array(
          'edit/(?:(?P<id>\d+)/)?' => 'AlmanacInterfaceEditController',
          'delete/(?:(?P<id>\d+)/)?' => 'AlmanacInterfaceDeleteController',
        ),
        'binding/' => array(
          'edit/(?:(?P<id>\d+)/)?' => 'AlmanacBindingEditController',
          'disable/(?:(?P<id>\d+)/)?' => 'AlmanacBindingDisableController',
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
    $cluster_caption = pht(
      'This permission is very dangerous. %s',
      phutil_tag(
        'a',
        array(
          'href' => PhabricatorEnv::getDoclink(
            'User Guide: Phabricator Clusters'),
          'target' => '_blank',
        ),
        pht('Learn More')));

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
      AlmanacManageClusterServicesCapability::CAPABILITY => array(
        'default' => PhabricatorPolicies::POLICY_NOONE,
        'caption' => $cluster_caption,
      ),
    );
  }

}
