<?php

final class PhabricatorSlowvoteApplication extends PhabricatorApplication {

  public function getBaseURI() {
    return '/vote/';
  }

  public function getFontIcon() {
    return 'fa-bar-chart';
  }

  public function getName() {
    return pht('Slowvote');
  }

  public function getShortDescription() {
    return pht('Conduct Polls');
  }

  public function getTitleGlyph() {
    return "\xE2\x9C\x94";
  }

  public function getHelpDocumentationArticles(PhabricatorUser $viewer) {
    return array(
      array(
        'name' => pht('Slowvote User Guide'),
        'href' => PhabricatorEnv::getDoclink('Slowvote User Guide'),
      ),
    );
  }

  public function getFlavorText() {
    return pht('Design by committee.');
  }

  public function getApplicationGroup() {
    return self::GROUP_UTILITIES;
  }

  public function getRemarkupRules() {
    return array(
      new SlowvoteRemarkupRule(),
    );
  }

  public function getRoutes() {
    return array(
      '/V(?P<id>[1-9]\d*)' => 'PhabricatorSlowvotePollController',
      '/vote/' => array(
        '(?:query/(?P<queryKey>[^/]+)/)?'
          => 'PhabricatorSlowvoteListController',
        'create/' => 'PhabricatorSlowvoteEditController',
        'edit/(?P<id>[1-9]\d*)/' => 'PhabricatorSlowvoteEditController',
        '(?P<id>[1-9]\d*)/' => 'PhabricatorSlowvoteVoteController',
        'comment/(?P<id>[1-9]\d*)/' => 'PhabricatorSlowvoteCommentController',
        'close/(?P<id>[1-9]\d*)/' => 'PhabricatorSlowvoteCloseController',
      ),
    );
  }

  protected function getCustomCapabilities() {
    return array(
      PhabricatorSlowvoteDefaultViewCapability::CAPABILITY => array(
        'caption' => pht('Default view policy for new polls.'),
        'template' => PhabricatorSlowvotePollPHIDType::TYPECONST,
      ),
    );
  }

}
