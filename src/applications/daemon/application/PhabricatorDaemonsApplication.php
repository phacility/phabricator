<?php

final class PhabricatorDaemonsApplication extends PhabricatorApplication {

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

  public function getIcon() {
    return 'fa-pied-piper-alt';
  }

  public function getApplicationGroup() {
    return self::GROUP_ADMIN;
  }

  public function canUninstall() {
    return false;
  }

  public function getEventListeners() {
    return array(
      new PhabricatorDaemonEventListener(),
    );
  }

  public function getRoutes() {
    return array(
      '/daemon/' => array(
        '' => 'PhabricatorDaemonConsoleController',
        'task/(?P<id>[1-9]\d*)/' => 'PhabricatorWorkerTaskDetailController',
        'log/' => array(
          '' => 'PhabricatorDaemonLogListController',
          '(?P<id>[1-9]\d*)/' => 'PhabricatorDaemonLogViewController',
        ),
        'bulk/' => array(
          '(?:query/(?P<queryKey>[^/]+)/)?' =>
            'PhabricatorDaemonBulkJobListController',
          'monitor/(?P<id>\d+)/' =>
            'PhabricatorDaemonBulkJobMonitorController',
          'view/(?P<id>\d+)/' =>
            'PhabricatorDaemonBulkJobViewController',

        ),
      ),
    );
  }

}
