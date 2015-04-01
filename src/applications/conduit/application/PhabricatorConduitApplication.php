<?php

final class PhabricatorConduitApplication extends PhabricatorApplication {

  public function getBaseURI() {
    return '/conduit/';
  }

  public function getFontIcon() {
    return 'fa-tty';
  }

  public function canUninstall() {
    return false;
  }

  public function getHelpDocumentationArticles(PhabricatorUser $viewer) {
    return array(
      array(
        'name' => pht('Conduit Technical Documentation'),
        'href' => PhabricatorEnv::getDoclink('Conduit Technical Documentation'),
      ),
    );
  }

  public function getName() {
    return pht('Conduit');
  }

  public function getShortDescription() {
    return pht('Developer API');
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
        '(?:query/(?P<queryKey>[^/]+)/)?' => 'PhabricatorConduitListController',
        'method/(?P<method>[^/]+)/' => 'PhabricatorConduitConsoleController',
        'log/' => 'PhabricatorConduitLogController',
        'log/view/(?P<view>[^/]+)/' => 'PhabricatorConduitLogController',
        'token/' => 'PhabricatorConduitTokenController',
        'token/edit/(?:(?P<id>\d+)/)?' =>
          'PhabricatorConduitTokenEditController',
        'token/terminate/(?:(?P<id>\d+)/)?' =>
          'PhabricatorConduitTokenTerminateController',
        'login/' => 'PhabricatorConduitTokenHandshakeController',
      ),
      '/api/(?P<method>[^/]+)' => 'PhabricatorConduitAPIController',
    );
  }

}
