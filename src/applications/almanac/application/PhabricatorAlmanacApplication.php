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

  public function getIconName() {
    return 'almanac';
  }

  public function getTitleGlyph() {
    return "\xE2\x98\x82";
  }

  public function getApplicationGroup() {
    return self::GROUP_UTILITIES;
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
      ),
    );
  }

  protected function getCustomCapabilities() {
    return array(
      AlmanacCreateServicesCapability::CAPABILITY => array(
        'default' => PhabricatorPolicies::POLICY_ADMIN,
      ),
    );
  }

}
