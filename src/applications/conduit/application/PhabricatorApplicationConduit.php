<?php

final class PhabricatorApplicationConduit extends PhabricatorApplication {

  public function getBaseURI() {
    return '/conduit/';
  }

  public function getIconName() {
    return 'conduit';
  }

  public function canUninstall() {
    return false;
  }

  public function getHelpURI() {
    return PhabricatorEnv::getDoclink(
      'article/Conduit_Technical_Documentation.html');
  }

  public function getShortDescription() {
    return 'Conduit API Console';
  }

  public function getTitleGlyph() {
    return "\xE2\x87\xB5";
  }

  public function getApplicationGroup() {
    return self::GROUP_DEVELOPER;
  }

  public function getApplicationOrder() {
    return 0.100;
  }

  public function getRoutes() {
    return array(
      '/conduit/' => array(
        '' => 'PhabricatorConduitListController',
        'method/(?P<method>[^/]+)/' => 'PhabricatorConduitConsoleController',
        'log/' => 'PhabricatorConduitLogController',
        'log/view/(?P<view>[^/]+)/' => 'PhabricatorConduitLogController',
        'token/' => 'PhabricatorConduitTokenController',
      ),
      '/api/(?P<method>[^/]+)' => 'PhabricatorConduitAPIController',
    );
  }

}
