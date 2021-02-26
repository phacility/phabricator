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
      array(
        'name' => pht('User Guide: Webhooks'),
        'href' => PhabricatorEnv::getDoclink('User Guide: Webhooks'),
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
        'disable/(?P<id>[1-9]\d*)/(?P<action>[^/]+)/'
          => 'HeraldDisableController',
        'test/' => 'HeraldTestConsoleController',
        'transcript/' => array(
          '' => 'HeraldTranscriptListController',
          '(?:query/(?P<queryKey>[^/]+)/)?' => 'HeraldTranscriptListController',
          '(?P<id>[1-9]\d*)/(?:(?P<view>[^/]+)/)?'
            => 'HeraldTranscriptController',
        ),
        'webhook/' => array(
          $this->getQueryRoutePattern() => 'HeraldWebhookListController',
          'view/(?P<id>\d+)/(?:request/(?P<requestID>[^/]+)/)?' =>
            'HeraldWebhookViewController',
          $this->getEditRoutePattern('edit/') => 'HeraldWebhookEditController',
          'test/(?P<id>\d+)/' => 'HeraldWebhookTestController',
          'key/(?P<action>view|cycle)/(?P<id>\d+)/' =>
            'HeraldWebhookKeyController',
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
      HeraldCreateWebhooksCapability::CAPABILITY => array(
        'default' => PhabricatorPolicies::POLICY_ADMIN,
      ),
    );
  }

}
