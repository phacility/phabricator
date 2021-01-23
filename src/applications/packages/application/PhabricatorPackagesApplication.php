<?php

final class PhabricatorPackagesApplication extends PhabricatorApplication {

  public function getName() {
    return pht('Packages');
  }

  public function getShortDescription() {
    return pht('Publish Software');
  }

  public function getFlavorText() {
    return pht('Applications and Extensions');
  }

  public function getBaseURI() {
    return '/packages/package/';
  }

  public function getIcon() {
    return 'fa-gift';
  }

  public function getApplicationGroup() {
    return self::GROUP_UTILITIES;
  }

  public function isPrototype() {
    return true;
  }

  protected function getCustomCapabilities() {
    return array(
      PhabricatorPackagesCreatePublisherCapability::CAPABILITY => array(
        'default' => PhabricatorPolicies::POLICY_ADMIN,
      ),
      PhabricatorPackagesPublisherDefaultEditCapability::CAPABILITY => array(
        'caption' => pht('Default edit policy for newly created publishers.'),
        'template' => PhabricatorPackagesPublisherPHIDType::TYPECONST,
        'default' => PhabricatorPolicies::POLICY_NOONE,
      ),
      PhabricatorPackagesPackageDefaultViewCapability::CAPABILITY => array(
        'caption' => pht('Default edit policy for newly created packages.'),
        'template' => PhabricatorPackagesPackagePHIDType::TYPECONST,
      ),
      PhabricatorPackagesPackageDefaultEditCapability::CAPABILITY => array(
        'caption' => pht('Default view policy for newly created packages.'),
        'template' => PhabricatorPackagesPackagePHIDType::TYPECONST,
        'default' => PhabricatorPolicies::POLICY_NOONE,
      ),
    );
  }
  public function getRoutes() {
    return array(
      '/package/' => array(
        '(?P<publisherKey>[^/]+)/' => array(
          '' => 'PhabricatorPackagesPublisherViewController',
          '(?P<packageKey>[^/]+)/' => array(
            '' => 'PhabricatorPackagesPackageViewController',
            '(?P<versionKey>[^/]+)/' =>
              'PhabricatorPackagesVersionViewController',
          ),
        ),
      ),
      '/packages/' => array(
        'publisher/' => array(
          $this->getQueryRoutePattern() =>
            'PhabricatorPackagesPublisherListController',
          $this->getEditRoutePattern('edit/') =>
            'PhabricatorPackagesPublisherEditController',
        ),
        'package/' => array(
          $this->getQueryRoutePattern() =>
            'PhabricatorPackagesPackageListController',
          $this->getEditRoutePattern('edit/') =>
            'PhabricatorPackagesPackageEditController',
        ),
        'version/' => array(
          $this->getQueryRoutePattern() =>
            'PhabricatorPackagesVersionListController',
          $this->getEditRoutePattern('edit/') =>
            'PhabricatorPackagesVersionEditController',
        ),
      ),
    );
  }

}
