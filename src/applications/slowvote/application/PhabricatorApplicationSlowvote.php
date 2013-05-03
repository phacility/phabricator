<?php

final class PhabricatorApplicationSlowvote extends PhabricatorApplication {

  public function getBaseURI() {
    return '/vote/';
  }

  public function getIconName() {
    return 'slowvote';
  }

  public function getShortDescription() {
    return 'Conduct Polls';
  }

  public function getTitleGlyph() {
    return "\xE2\x9C\x94";
  }

  public function getHelpURI() {
    return PhabricatorEnv::getDoclink('article/Slowvote_User_Guide.html');
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
        '(?:view/(?P<view>\w+)/)?' => 'PhabricatorSlowvoteListController',
        'create/' => 'PhabricatorSlowvoteCreateController',
        '(?P<id>[1-9]\d*)/' => 'PhabricatorSlowvoteVoteController',
      ),
    );
  }

}
