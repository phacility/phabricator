#!/usr/bin/env php
<?php

$root = dirname(dirname(dirname(__FILE__)));
require_once $root.'/scripts/__init_script__.php';

if ($argc !== 2 || $argv[1] === '--help') {
  echo "Usage: aphrontpath.php <url>\n";
  echo "Purpose: Print controller which will process passed <url>.\n";
  exit(1);
}

$url = parse_url($argv[1]);
$path = '/'.(isset($url['path']) ? ltrim($url['path'], '/') : '');

$config_key = 'aphront.default-application-configuration-class';
$application = PhabricatorEnv::newObjectFromConfig($config_key);
$application->setRequest(new AphrontRequest('', $path));

list($controller) = $application->buildControllerForPath($path);
if (!$controller && substr($path, -1) !== '/') {
  list($controller) = $application->buildControllerForPath($path.'/');
}
if ($controller) {
  echo get_class($controller) . "\n";
}
