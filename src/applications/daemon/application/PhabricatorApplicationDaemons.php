<?php

final class PhabricatorApplicationDaemons extends PhabricatorApplication {

  public function getName() {
    return pht('Daemons');
  }

  public function getShortDescription() {
    return pht('Manage Daemons');
  }

  public function getBaseURI() {
    return '/daemon/';
  }

  public function getTitleGlyph() {
    return "\xE2\x98\xAF";
  }

  public function getIconName() {
    return 'daemon';
  }

  public function getApplicationGroup() {
    return self::GROUP_ADMIN;
  }

  public function canUninstall() {
    return false;
  }

  public function getRoutes() {
    return array(
      '/daemon/' => array(
        '' => 'PhabricatorDaemonConsoleController',
        'task/(?P<id>[1-9]\d*)/' => 'PhabricatorWorkerTaskDetailController',
        'task/(?P<id>[1-9]\d*)/(?P<action>[^/]+)/'
          => 'PhabricatorWorkerTaskUpdateController',
        'log/' => array(
          '' => 'PhabricatorDaemonLogListController',
          'combined/' => 'PhabricatorDaemonCombinedLogController',
          '(?P<id>[1-9]\d*)/' => 'PhabricatorDaemonLogViewController',
        ),
      ),
    );
  }

}
