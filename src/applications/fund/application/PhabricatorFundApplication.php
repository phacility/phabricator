<?php

final class PhabricatorFundApplication extends PhabricatorApplication {

  public function getName() {
    return pht('Fund');
  }

  public function getBaseURI() {
    return '/fund/';
  }

  public function getShortDescription() {
    return pht('Donate');
  }

  public function getIcon() {
    return 'fa-heart';
  }

  public function getTitleGlyph() {
    return "\xE2\x99\xA5";
  }

  public function getApplicationGroup() {
    return self::GROUP_UTILITIES;
  }

  public function isPrototype() {
    return true;
  }

  public function getRemarkupRules() {
    return array(
      new FundInitiativeRemarkupRule(),
    );
  }

  public function getRoutes() {
    return array(
      '/I(?P<id>[1-9]\d*)' => 'FundInitiativeViewController',
      '/fund/' => array(
        '(?:query/(?P<queryKey>[^/]+)/)?' => 'FundInitiativeListController',
        'create/' => 'FundInitiativeEditController',
        $this->getEditRoutePattern('edit/')
          => 'FundInitiativeEditController',
        'close/(?P<id>\d+)/' => 'FundInitiativeCloseController',
        'back/(?P<id>\d+)/' => 'FundInitiativeBackController',
        'backers/(?:(?P<id>\d+)/)?(?:query/(?P<queryKey>[^/]+)/)?'
          => 'FundBackerListController',
      ),
    );
  }

  protected function getCustomCapabilities() {
    return array(
      FundDefaultViewCapability::CAPABILITY => array(
        'caption' => pht('Default view policy for newly created initiatives.'),
        'template' => FundInitiativePHIDType::TYPECONST,
      ),
      FundCreateInitiativesCapability::CAPABILITY => array(
        'default' => PhabricatorPolicies::POLICY_ADMIN,
      ),
    );
  }

  public function getApplicationSearchDocumentTypes() {
    return array(
      FundInitiativePHIDType::TYPECONST,
    );
  }

}
