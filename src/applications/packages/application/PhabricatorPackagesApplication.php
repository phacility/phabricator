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
    return '/packages/';
  }

  public function getIcon() {
    return 'fa-gift';
  }

  public function isPrototype() {
    return true;
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
