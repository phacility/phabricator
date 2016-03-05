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

  public function getIcon() {
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
        '(?P<type>blueprint)/' => array(
          '(?:query/(?P<queryKey>[^/]+)/)?' => 'DrydockBlueprintListController',
          '(?P<id>[1-9]\d*)/' => array(
            '' => 'DrydockBlueprintViewController',
            '(?P<action>disable|enable)/' =>
              'DrydockBlueprintDisableController',
            'resources/(?:query/(?P<queryKey>[^/]+)/)?' =>
              'DrydockResourceListController',
            'logs/(?:query/(?P<queryKey>[^/]+)/)?' =>
              'DrydockLogListController',
            'authorizations/(?:query/(?P<queryKey>[^/]+)/)?' =>
              'DrydockAuthorizationListController',
          ),
          $this->getEditRoutePattern('edit/')
            => 'DrydockBlueprintEditController',
        ),
        '(?P<type>resource)/' => array(
          '(?:query/(?P<queryKey>[^/]+)/)?' => 'DrydockResourceListController',
          '(?P<id>[1-9]\d*)/' => array(
            '' => 'DrydockResourceViewController',
            'release/' => 'DrydockResourceReleaseController',
            'leases/(?:query/(?P<queryKey>[^/]+)/)?' =>
              'DrydockLeaseListController',
            'logs/(?:query/(?P<queryKey>[^/]+)/)?' =>
              'DrydockLogListController',
          ),
        ),
        '(?P<type>lease)/' => array(
          '(?:query/(?P<queryKey>[^/]+)/)?' => 'DrydockLeaseListController',
          '(?P<id>[1-9]\d*)/' => array(
            '' => 'DrydockLeaseViewController',
            'release/' => 'DrydockLeaseReleaseController',
            'logs/(?:query/(?P<queryKey>[^/]+)/)?' =>
              'DrydockLogListController',
          ),
        ),
        '(?P<type>authorization)/' => array(
          '(?P<id>[1-9]\d*)/' => array(
            '' => 'DrydockAuthorizationViewController',
            '(?P<action>authorize|decline)/' =>
              'DrydockAuthorizationAuthorizeController',
          ),
        ),
        '(?P<type>operation)/' => array(
          '(?:query/(?P<queryKey>[^/]+)/)?'
            => 'DrydockRepositoryOperationListController',
          '(?P<id>[1-9]\d*)/' => array(
            '' => 'DrydockRepositoryOperationViewController',
            'status/' => 'DrydockRepositoryOperationStatusController',
            'dismiss/' => 'DrydockRepositoryOperationDismissController',
          ),
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
