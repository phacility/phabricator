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

  public function getRemarkupRules() {
    return array(
      new HarbormasterRemarkupRule(),
    );
  }

  public function getHelpDocumentationArticles(PhabricatorUser $viewer) {
    return array(
      array(
        'name' => pht('Harbormaster User Guide'),
        'href' => PhabricatorEnv::getDoclink('Harbormaster User Guide'),
      ),
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
          '(?P<id>\d+)/(?P<action>pause|resume|restart|abort)/'
            => 'HarbormasterBuildableActionController',
        ),
        'build/' => array(
          '(?P<id>\d+)/' => 'HarbormasterBuildViewController',
          '(?P<action>pause|resume|restart|abort)/'.
            '(?P<id>\d+)/(?:(?P<via>[^/]+)/)?'
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
        'unit/' => array(
          '(?P<id>\d+)/' => 'HarbormasterUnitMessagesController',
        ),
        'lint/' => array(
          '(?P<id>\d+)/' => 'HarbormasterLintMessagesController',
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
