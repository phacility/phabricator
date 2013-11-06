<?php

final class PhabricatorApplicationHarbormaster extends PhabricatorApplication {

  public function getBaseURI() {
    return '/harbormaster/';
  }

  public function getShortDescription() {
    return pht('Continuous Build');
  }

  public function getIconName() {
    return 'harbormaster';
  }

  public function getTitleGlyph() {
    return "\xE2\x99\xBB";
  }

  public function getFlavorText() {
    return pht('Ship Some Freight');
  }

  public function getApplicationGroup() {
    return self::GROUP_UTILITIES;
  }

  public function isBeta() {
    return true;
  }

  public function getRemarkupRules() {
    return array(
      new HarbormasterRemarkupRule(),
    );
  }

  public function getRoutes() {
    return array(
      '/B(?P<id>[1-9]\d*)' => 'HarbormasterBuildableViewController',
      '/harbormaster/' => array(
        '(?:query/(?P<queryKey>[^/]+)/)?'
          => 'HarbormasterBuildableListController',
        'buildable/' => array(
          'edit/(?:(?P<id>\d+)/)?' => 'HarbormasterBuildableEditController',
          'apply/(?:(?P<id>\d+)/)?' => 'HarbormasterBuildableApplyController',
        ),
        'step/' => array(
          'add/(?:(?P<id>\d+)/)?' => 'HarbormasterStepAddController',
          'edit/(?:(?P<id>\d+)/)?' => 'HarbormasterStepEditController',
          'delete/(?:(?P<id>\d+)/)?' => 'HarbormasterStepDeleteController',
        ),
        'plan/' => array(
          '(?:query/(?P<queryKey>[^/]+)/)?'
            => 'HarbormasterPlanListController',
          'edit/(?:(?P<id>\d+)/)?' => 'HarbormasterPlanEditController',
          '(?P<id>\d+)/' => 'HarbormasterPlanViewController',
        ),
      ),
    );
  }

  public function getCustomCapabilities() {
    return array(
      HarbormasterCapabilityManagePlans::CAPABILITY => array(
        'caption' => pht('Can create and manage build plans.'),
        'default' => PhabricatorPolicies::POLICY_ADMIN,
      ),
    );
  }

}
