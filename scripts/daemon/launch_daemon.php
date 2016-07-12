#!/usr/bin/env php
<?php

// NOTE: This is substantially the same as the libphutil/ "launch_daemon.php"
// script, except it loads the Phabricator environment and adds some Phabricator
// specific flags.

declare(ticks = 1);

$root = dirname(dirname(dirname(__FILE__)));
require_once $root.'/scripts/__init_script__.php';

$overseer = new PhutilDaemonOverseer($argv);

$bootloader = PhutilBootloader::getInstance();
foreach ($bootloader->getAllLibraries() as $library) {
  $overseer->addLibrary(phutil_get_library_root($library));
}

$overseer->run();
