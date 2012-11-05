<?php

abstract class PhabricatorDaemon extends PhutilDaemon {

  protected function willRun() {
    parent::willRun();

    // This stores unbounded amounts of log data; make it discard instead so
    // that daemons do not require unbounded amounts of memory.
    DarkConsoleErrorLogPluginAPI::enableDiscardMode();

    // Also accumulates potentially unlimited amounts of data.
    DarkConsoleEventPluginAPI::enableDiscardMode();

    $phabricator = phutil_get_library_root('phabricator');
    $root = dirname($phabricator);
    require_once $root.'/scripts/__init_script__.php';
  }
}
