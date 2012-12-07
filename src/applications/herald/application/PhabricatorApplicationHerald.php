<?php

final class PhabricatorApplicationHerald extends PhabricatorApplication {

  public function getBaseURI() {
    return '/herald/';
  }

  public function getIconName() {
    return 'herald';
  }

  public function getShortDescription() {
    return 'Create Notification Rules';
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
        '' => 'HeraldHomeController',
        'view/(?P<content_type>[^/]+)/(?:(?P<rule_type>[^/]+)/)?'
          => 'HeraldHomeController',
        'new/(?:(?P<type>[^/]+)/(?:(?P<rule_type>[^/]+)/)?)?'
          => 'HeraldNewController',
        'rule/(?:(?P<id>[1-9]\d*)/)?' => 'HeraldRuleController',
        'history/(?:(?P<id>[1-9]\d*)/)?' => 'HeraldRuleEditHistoryController',
        'delete/(?P<id>[1-9]\d*)/' => 'HeraldDeleteController',
        'test/' => 'HeraldTestConsoleController',
        'transcript/' => 'HeraldTranscriptListController',
        'transcript/(?P<id>[1-9]\d*)/(?:(?P<filter>\w+)/)?'
          => 'HeraldTranscriptController',
      ),
    );
  }

}
