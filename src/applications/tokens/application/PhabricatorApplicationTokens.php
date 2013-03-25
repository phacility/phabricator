<?php

final class PhabricatorApplicationTokens extends PhabricatorApplication {

  public function getName() {
    return pht('Tokens');
  }

  public function isBeta() {
    return true;
  }

  public function getBaseURI() {
    return '/token/';
  }

  public function getIconName() {
    return 'token';
  }

  public function getTitleGlyph() {
    return "\xE2\x99\xA6";
  }

  public function getShortDescription() {
    return pht('Acquire Trinkets');
  }

  public function getApplicationGroup() {
    return self::GROUP_UTILITIES;
  }

  public function getRoutes() {
    return array(
      '/token/' => array(
        '' => 'PhabricatorTokenGivenController',
        'given/' => 'PhabricatorTokenGivenController',
        'give/(?<phid>[^/]+)/' => 'PhabricatorTokenGiveController',
        'leaders/' => 'PhabricatorTokenLeaderController',
      ),
    );
  }

  public function getEventListeners() {
    return array(
      new PhabricatorTokenUIEventListener(),
    );
  }

}
