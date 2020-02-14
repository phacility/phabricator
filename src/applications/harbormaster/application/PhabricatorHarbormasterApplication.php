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

  public function getIcon() {
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
          'view/(?P<id>\d+)/' => 'HarbormasterStepViewController',
          'edit/(?:(?P<id>\d+)/)?' => 'HarbormasterStepEditController',
          'delete/(?:(?P<id>\d+)/)?' => 'HarbormasterStepDeleteController',
        ),
        'buildable/' => array(
          '(?P<id>\d+)/(?P<action>pause|resume|restart|abort)/'
            => 'HarbormasterBuildableActionController',
        ),
        'build/' => array(
          $this->getQueryRoutePattern() => 'HarbormasterBuildListController',
          '(?P<id>\d+)/(?:(?P<generation>\d+)/)?'
            => 'HarbormasterBuildViewController',
          '(?P<action>pause|resume|restart|abort)/'.
            '(?P<id>\d+)/(?:(?P<via>[^/]+)/)?'
            => 'HarbormasterBuildActionController',
        ),
        'plan/' => array(
          $this->getQueryRoutePattern() => 'HarbormasterPlanListController',
          $this->getEditRoutePattern('edit/')
            => 'HarbormasterPlanEditController',
          'disable/(?P<id>\d+)/' => 'HarbormasterPlanDisableController',
          'behavior/(?P<id>\d+)/(?P<behaviorKey>[^/]+)/' =>
             'HarbormasterPlanBehaviorController',
          'run/(?P<id>\d+)/' => 'HarbormasterPlanRunController',
          '(?P<id>\d+)/' => 'HarbormasterPlanViewController',
        ),
        'unit/' => array(
          '(?P<id>\d+)/' => 'HarbormasterUnitMessageListController',
          'view/(?P<id>\d+)/' => 'HarbormasterUnitMessageViewController',
        ),
        'lint/' => array(
          '(?P<id>\d+)/' => 'HarbormasterLintMessagesController',
        ),
        'hook/' => array(
          'circleci/' => 'HarbormasterCircleCIHookController',
          'buildkite/' => 'HarbormasterBuildkiteHookController',
        ),
        'log/' => array(
          'view/(?P<id>\d+)/(?:\$(?P<lines>\d+(?:-\d+)?))?'
            => 'HarbormasterBuildLogViewController',
          'render/(?P<id>\d+)/(?:\$(?P<lines>\d+(?:-\d+)?))?'
            => 'HarbormasterBuildLogRenderController',
          'download/(?P<id>\d+)/' => 'HarbormasterBuildLogDownloadController',
        ),
      ),
    );
  }

  protected function getCustomCapabilities() {
    return array(
      HarbormasterCreatePlansCapability::CAPABILITY => array(
        'default' => PhabricatorPolicies::POLICY_ADMIN,
      ),
      HarbormasterBuildPlanDefaultViewCapability::CAPABILITY => array(
        'template' => HarbormasterBuildPlanPHIDType::TYPECONST,
        'capability' => PhabricatorPolicyCapability::CAN_VIEW,
      ),
      HarbormasterBuildPlanDefaultEditCapability::CAPABILITY => array(
        'template' => HarbormasterBuildPlanPHIDType::TYPECONST,
        'capability' => PhabricatorPolicyCapability::CAN_EDIT,
        'default' => PhabricatorPolicies::POLICY_ADMIN,
      ),
    );
  }

}
