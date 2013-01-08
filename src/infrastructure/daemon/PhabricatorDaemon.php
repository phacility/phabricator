<?php

abstract class PhabricatorDaemon extends PhutilDaemon {

  protected function willRun() {
    parent::willRun();

    $phabricator = phutil_get_library_root('phabricator');
    $root = dirname($phabricator);
    require_once $root.'/scripts/__init_script__.php';
  }
}
