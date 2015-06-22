<?php

final class PhabricatorDrydockApplication extends PhabricatorApplication {

  public function getBaseURI() {
    return '/drydock/';
  }

  public function getName() {
    return pht('Drydock');
  }

  public function getShortDescription() {
    return pht('Allocate Software Resources');
  }

  public function getFontIcon() {
    return 'fa-truck';
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

  public function isPrototype() {
    return true;
  }

  public function getHelpDocumentationArticles(PhabricatorUser $viewer) {
    return array(
      array(
        'name' => pht('Drydock User Guide'),
        'href' => PhabricatorEnv::getDoclink('Drydock User Guide'),
      ),
    );
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
      DrydockDefaultViewCapability::CAPABILITY => array(
        'template' => DrydockBlueprintPHIDType::TYPECONST,
        'capability' => PhabricatorPolicyCapability::CAN_VIEW,
      ),
      DrydockDefaultEditCapability::CAPABILITY => array(
        'default' => PhabricatorPolicies::POLICY_ADMIN,
        'template' => DrydockBlueprintPHIDType::TYPECONST,
        'capability' => PhabricatorPolicyCapability::CAN_EDIT,
      ),
      DrydockCreateBlueprintsCapability::CAPABILITY => array(
        'default' => PhabricatorPolicies::POLICY_ADMIN,
      ),
    );
  }

}
