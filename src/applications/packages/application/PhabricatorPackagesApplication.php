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
        ),
      ),
      '/packages/' => array(
        'publisher/' => array(
          $this->getQueryRoutePattern() =>
            'PhabricatorPackagesPublisherListController',
          $this->getEditRoutePattern('edit/') =>
            'PhabricatorPackagesPublisherEditController',
        ),
      ),
    );
  }

}
