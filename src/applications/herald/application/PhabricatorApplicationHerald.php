<?php

final class PhabricatorApplicationHerald extends PhabricatorApplication {

  const CAN_CREATE_RULE = 'herald.create';
  const CAN_CREATE_GLOBAL_RULE = 'herald.global';

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
    return PhabricatorEnv::getDoclink('article/Herald_User_Guide.html');
  }

  public function getFlavorText() {
    return pht('Watch for danger!');
  }

  public function getApplicationGroup() {
    return self::GROUP_ORGANIZATION;
  }

  public function getRoutes() {
    return array(
      '/herald/' => array(
        '(?:query/(?P<queryKey>[^/]+)/)?' => 'HeraldRuleListController',
        'new/(?:(?P<type>[^/]+)/(?:(?P<rule_type>[^/]+)/)?)?'
          => 'HeraldNewController',
        'rule/(?P<id>[1-9]\d*)/' => 'HeraldRuleViewController',
        'edit/(?:(?P<id>[1-9]\d*)/)?' => 'HeraldRuleController',
        'history/(?:(?P<id>[1-9]\d*)/)?' => 'HeraldRuleEditHistoryController',
        'delete/(?P<id>[1-9]\d*)/' => 'HeraldDeleteController',
        'test/' => 'HeraldTestConsoleController',
        'transcript/' => 'HeraldTranscriptListController',
        'transcript/(?P<id>[1-9]\d*)/(?:(?P<filter>\w+)/)?'
          => 'HeraldTranscriptController',
      ),
    );
  }

  protected function getCustomCapabilities() {
    return array(
      self::CAN_CREATE_RULE => array(
        'label' => pht('Can Create Rules'),
      ),
      self::CAN_CREATE_GLOBAL_RULE => array(
        'label' => pht('Can Create Global Rules'),
        'caption' => pht('Global rules can bypass access controls.'),
        'default' => PhabricatorPolicies::POLICY_ADMIN,
      ),
    );
  }


}
