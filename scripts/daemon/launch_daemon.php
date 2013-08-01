#!/usr/bin/env php
<?php

// NOTE: This is substantially the same as the libphutil/ "launch_daemon.php"
// script, except it loads the Phabricator environment and adds some Phabricator
// specific flags.

$root = dirname(dirname(dirname(__FILE__)));
require_once $root.'/scripts/__init_script__.php';

$flags = array();

$bootloader = PhutilBootloader::getInstance();
foreach ($bootloader->getAllLibraries() as $library) {
  if ($library == 'phutil') {
    // No need to load libphutil, it's necessarily loaded implicitly by the
    // daemon itself.
    continue;
  }
  $flags[] = '--load-phutil-library='.phutil_get_library_root($library);
}

// Add more flags.
array_splice($argv, 2, 0, $flags);

$overseer = new PhutilDaemonOverseer($argv);
$overseer->run();
