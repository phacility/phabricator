<?php

final class PhabricatorApplicationHerald extends PhabricatorApplication {

  public function getBaseURI() {
    return '/herald/';
  }

  public function getIconName() {
    return 'herald';
  }

  public function getShortDescription() {
    return pht('Create Notification Rules');
  }

  public function getTitleGlyph() {
    return "\xE2\x98\xBF";
  }

  public function getHelpURI() {
    return PhabricatorEnv::getDoclink('Herald User Guide');
  }

  public function getFlavorText() {
    return pht('Watch for danger!');
  }

  public function getApplicationGroup() {
    return self::GROUP_ORGANIZATION;
  }

  public function getRemarkupRules() {
    return array(
      new HeraldRemarkupRule(),
    );
  }

  public function getRoutes() {
    return array(
      '/herald/' => array(
        '(?:query/(?P<queryKey>[^/]+)/)?' => 'HeraldRuleListController',
        'new/' => 'HeraldNewController',
        'rule/(?P<id>[1-9]\d*)/' => 'HeraldRuleViewController',
        'edit/(?:(?P<id>[1-9]\d*)/)?' => 'HeraldRuleController',
        'disable/(?P<id>[1-9]\d*)/(?P<action>\w+)/' =>
          'HeraldDisableController',
        'history/(?:(?P<id>[1-9]\d*)/)?' => 'HeraldRuleEditHistoryController',
        'test/' => 'HeraldTestConsoleController',
        'transcript/' => array(
          '' => 'HeraldTranscriptListController',
          '(?:query/(?P<queryKey>[^/]+)/)?' => 'HeraldTranscriptListController',
          '(?P<id>[1-9]\d*)/(?:(?P<filter>\w+)/)?'
          => 'HeraldTranscriptController',
        )
      )
    );
  }

  protected function getCustomCapabilities() {
    return array(
      HeraldCapabilityManageGlobalRules::CAPABILITY => array(
        'caption' => pht('Global rules can bypass access controls.'),
        'default' => PhabricatorPolicies::POLICY_ADMIN,
      ),
    );
  }


}
