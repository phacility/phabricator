<?php

final class PhabricatorApplicationXHProf extends PhabricatorApplication {

  public function getBaseURI() {
    return '/xhprof/';
  }

  public function getShortDescription() {
    return pht('PHP Profiling Tool');
  }

  public function getIconName() {
    return 'xhprof';
  }

  public function getTitleGlyph() {
    return "\xE2\x98\x84";
  }

  public function getApplicationGroup() {
    return self::GROUP_DEVELOPER;
  }

  public function getRoutes() {
    return array(
      '/xhprof/' => array(
        '' => 'PhabricatorXHProfSampleListController',
        'list/(?P<view>[^/]+)/' => 'PhabricatorXHProfSampleListController',
        'profile/(?P<phid>[^/]+)/' => 'PhabricatorXHProfProfileController',
      ),
    );
  }


}
