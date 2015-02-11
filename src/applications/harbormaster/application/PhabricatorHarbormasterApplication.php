<?php

final class PhabricatorHarbormasterApplication extends PhabricatorApplication {

  public function getBaseURI() {
    return '/harbormaster/';
  }

  public function getName() {
    return pht('Harbormaster');
  }

  public function getShortDescription() {
    return pht('Build/CI');
  }

  public function getFontIcon() {
    return 'fa-ship';
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

  public function getEventListeners() {
    return array(
      new HarbormasterUIEventListener(),
    );
  }

  public function isPrototype() {
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
        'step/' => array(
          'add/(?:(?P<id>\d+)/)?' => 'HarbormasterStepAddController',
          'new/(?P<plan>\d+)/(?P<class>[^/]+)/'
            => 'HarbormasterStepEditController',
          'edit/(?:(?P<id>\d+)/)?' => 'HarbormasterStepEditController',
          'delete/(?:(?P<id>\d+)/)?' => 'HarbormasterStepDeleteController',
        ),
        'buildable/' => array(
          '(?P<id>\d+)/(?P<action>stop|resume|restart)/'
            => 'HarbormasterBuildableActionController',
        ),
        'build/' => array(
          '(?P<id>\d+)/' => 'HarbormasterBuildViewController',
          '(?P<action>stop|resume|restart)/(?P<id>\d+)/(?:(?P<via>[^/]+)/)?'
            => 'HarbormasterBuildActionController',
        ),
        'plan/' => array(
          '(?:query/(?P<queryKey>[^/]+)/)?'
            => 'HarbormasterPlanListController',
          'edit/(?:(?P<id>\d+)/)?' => 'HarbormasterPlanEditController',
          'order/(?:(?P<id>\d+)/)?' => 'HarbormasterPlanOrderController',
          'disable/(?P<id>\d+)/' => 'HarbormasterPlanDisableController',
          'run/(?P<id>\d+)/' => 'HarbormasterPlanRunController',
          '(?P<id>\d+)/' => 'HarbormasterPlanViewController',
        ),
      ),
    );
  }

  protected function getCustomCapabilities() {
    return array(
      HarbormasterManagePlansCapability::CAPABILITY => array(
        'caption' => pht('Can create and manage build plans.'),
        'default' => PhabricatorPolicies::POLICY_ADMIN,
      ),
    );
  }

}
