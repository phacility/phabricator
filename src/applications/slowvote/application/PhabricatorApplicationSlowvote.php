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
    return PhabricatorEnv::getDoclink('Slowvote User Guide');
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
      ),
    );
  }

  public function getCustomCapabilities() {
    return array(
      PhabricatorSlowvoteCapabilityDefaultView::CAPABILITY => array(
        'caption' => pht('Default view policy for new polls.'),
      ),
    );
  }

}
