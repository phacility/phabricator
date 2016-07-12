<?php

final class PhabricatorHeraldApplication extends PhabricatorApplication {

  public function getBaseURI() {
    return '/herald/';
  }

  public function getIcon() {
    return 'fa-bullhorn';
  }

  public function getName() {
    return pht('Herald');
  }

  public function getShortDescription() {
    return pht('Create Notification Rules');
  }

  public function getTitleGlyph() {
    return "\xE2\x98\xBF";
  }

  public function getHelpDocumentationArticles(PhabricatorUser $viewer) {
    return array(
      array(
        'name' => pht('Herald User Guide'),
        'href' => PhabricatorEnv::getDoclink('Herald User Guide'),
      ),
    );
  }

  public function getFlavorText() {
    return pht('Watch for danger!');
  }

  public function getApplicationGroup() {
    return self::GROUP_UTILITIES;
  }

  public function getRemarkupRules() {
    return array(
      new HeraldRemarkupRule(),
    );
  }

  public function getRoutes() {
    return array(
      '/H(?P<id>[1-9]\d*)' => 'HeraldRuleViewController',
      '/herald/' => array(
        '(?:query/(?P<queryKey>[^/]+)/)?' => 'HeraldRuleListController',
        'new/' => 'HeraldNewController',
        'create/' => 'HeraldNewController',
        'edit/(?:(?P<id>[1-9]\d*)/)?' => 'HeraldRuleController',
        'disable/(?P<id>[1-9]\d*)/(?P<action>\w+)/'
          => 'HeraldDisableController',
        'test/' => 'HeraldTestConsoleController',
        'transcript/' => array(
          '' => 'HeraldTranscriptListController',
          '(?:query/(?P<queryKey>[^/]+)/)?' => 'HeraldTranscriptListController',
          '(?P<id>[1-9]\d*)/'
            => 'HeraldTranscriptController',
        ),
      ),
    );
  }

  protected function getCustomCapabilities() {
    return array(
      HeraldManageGlobalRulesCapability::CAPABILITY => array(
        'caption' => pht('Global rules can bypass access controls.'),
        'default' => PhabricatorPolicies::POLICY_ADMIN,
      ),
    );
  }

}
