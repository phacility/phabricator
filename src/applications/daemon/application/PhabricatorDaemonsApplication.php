<?php

final class PhabricatorDaemonsApplication extends PhabricatorApplication {

  public function getName() {
    return pht('Daemons');
  }

  public function getShortDescription() {
    return pht('Manage Phabricator Daemons');
  }

  public function getBaseURI() {
    return '/daemon/';
  }

  public function getTitleGlyph() {
    return "\xE2\x98\xAF";
  }

  public function getFontIcon() {
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
        'event/(?P<id>[1-9]\d*)/' => 'PhabricatorDaemonLogEventViewController',
      ),
    );
  }

}
