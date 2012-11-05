<?php

final class PhabricatorApplicationDaemons extends PhabricatorApplication {

  public function getName() {
    return 'Daemon Console';
  }

  public function getShortDescription() {
    return 'Manage Daemons';
  }

  public function getBaseURI() {
    return '/daemon/';
  }

  public function getTitleGlyph() {
    return "\xE2\x98\xAF";
  }

  public function getAutospriteName() {
    return 'daemons';
  }

  public function getApplicationGroup() {
    return self::GROUP_ADMIN;
  }

  public function getRoutes() {
    return array(
      '/daemon/' => array(
        'task/(?P<id>[1-9]\d*)/' => 'PhabricatorWorkerTaskDetailController',
        'task/(?P<id>[1-9]\d*)/(?P<action>[^/]+)/'
          => 'PhabricatorWorkerTaskUpdateController',
        'log/' => array(
          '(?P<running>running/)?' => 'PhabricatorDaemonLogListController',
          'combined/' => 'PhabricatorDaemonCombinedLogController',
          '(?P<id>[1-9]\d*)/' => 'PhabricatorDaemonLogViewController',
        ),
        'timeline/' => 'PhabricatorDaemonTimelineConsoleController',
        'timeline/(?P<id>[1-9]\d*)/'
          => 'PhabricatorDaemonTimelineEventController',
        '' => 'PhabricatorDaemonConsoleController',
      ),
    );
  }

}
