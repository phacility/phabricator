<?php

final class PhabricatorApplicationDrydock extends PhabricatorApplication {

  public function getBaseURI() {
    return '/drydock/';
  }

  public function getShortDescription() {
    return pht('Allocate Software Resources');
  }

  public function getIconName() {
    return 'drydock';
  }

  public function getTitleGlyph() {
    return "\xE2\x98\x82";
  }

  public function getFlavorText() {
    return pht('A nautical adventure.');
  }

  public function getApplicationGroup() {
    return self::GROUP_UTILITIES;
  }

  public function isBeta() {
    return true;
  }

  public function getRoutes() {
    return array(
      '/drydock/' => array(
        '' => 'DrydockConsoleController',
        'blueprint/' => array(
          '(?:query/(?P<queryKey>[^/]+)/)?' => 'DrydockBlueprintListController',
          '(?P<id>[1-9]\d*)/' => 'DrydockBlueprintViewController',
          'create/' => 'DrydockBlueprintCreateController',
          'edit/(?:(?P<id>[1-9]\d*)/)?' => 'DrydockBlueprintEditController',
        ),
        'resource/' => array(
          '(?:query/(?P<queryKey>[^/]+)/)?' => 'DrydockResourceListController',
          '(?P<id>[1-9]\d*)/' => 'DrydockResourceViewController',
          '(?P<id>[1-9]\d*)/close/' => 'DrydockResourceCloseController',
        ),
        'lease/' => array(
          '(?:query/(?P<queryKey>[^/]+)/)?' => 'DrydockLeaseListController',
          '(?P<id>[1-9]\d*)/' => 'DrydockLeaseViewController',
          '(?P<id>[1-9]\d*)/release/' => 'DrydockLeaseReleaseController',
        ),
        'log/' => array(
          '(?:query/(?P<queryKey>[^/]+)/)?' => 'DrydockLogListController',
        ),
      ),
    );
  }

  protected function getCustomCapabilities() {
    return array(
      DrydockCapabilityDefaultView::CAPABILITY => array(
      ),
      DrydockCapabilityDefaultEdit::CAPABILITY => array(
        'default' => PhabricatorPolicies::POLICY_ADMIN,
      ),
      DrydockCapabilityCreateBlueprints::CAPABILITY => array(
        'default' => PhabricatorPolicies::POLICY_ADMIN,
      ),
    );
  }


}
