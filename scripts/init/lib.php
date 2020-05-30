<?php

function init_phabricator_script(array $options) {
  error_reporting(E_ALL | E_STRICT);
  ini_set('display_errors', 1);

  $include_path = ini_get('include_path');
  ini_set(
    'include_path',
    $include_path.PATH_SEPARATOR.dirname(__FILE__).'/../../../');

  $ok = @include_once 'arcanist/support/init/init-script.php';
  if (!$ok) {
    echo
      'FATAL ERROR: Unable to load the "Arcanist" library. '.
      'Put "arcanist/" next to "phabricator/" on disk.';
    echo "\n";

    exit(1);
  }

  phutil_load_library('arcanist/src');
  phutil_load_library(dirname(__FILE__).'/../../src/');

  $config_optional = $options['config.optional'];
  PhabricatorEnv::initializeScriptEnvironment($config_optional);
}
