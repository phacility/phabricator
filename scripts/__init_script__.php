<?php

error_reporting(E_ALL | E_STRICT);
ini_set('display_errors', 1);

$include_path = ini_get('include_path');
ini_set(
  'include_path',
  $include_path.PATH_SEPARATOR.dirname(__FILE__).'/../../');
@include_once 'libphutil/scripts/__init_script__.php';
if (!@constant('__LIBPHUTIL__')) {
  echo "ERROR: Unable to load libphutil. Update your PHP 'include_path' to ".
       "include the parent directory of libphutil/.\n";
  exit(1);
}

phutil_load_library(dirname(__FILE__).'/../src/');

// NOTE: This is dangerous in general, but we know we're in a script context and
// are not vulnerable to CSRF.
AphrontWriteGuard::allowDangerousUnguardedWrites(true);

require_once dirname(dirname(__FILE__)).'/conf/__init_conf__.php';

$env = isset($_SERVER['PHABRICATOR_ENV'])
  ? $_SERVER['PHABRICATOR_ENV']
  : getenv('PHABRICATOR_ENV');
if (!$env) {
  echo phutil_console_wrap(
    phutil_console_format(
      "**ERROR**: PHABRICATOR_ENV Not Set\n\n".
      "Define the __PHABRICATOR_ENV__ environment variable before running ".
      "this script. You can do it on the command line like this:\n\n".
      "  $ PHABRICATOR_ENV=__custom/myconfig__ %s ...\n\n".
      "Replace __custom/myconfig__ with the path to your configuration file. ".
      "For more information, see the 'Configuration Guide' in the ".
      "Phabricator documentation.\n\n",
      $argv[0]));
  exit(1);
}

$conf = phabricator_read_config_file($env);
$conf['phabricator.env'] = $env;

PhabricatorEnv::setEnvConfig($conf);

phutil_load_library('arcanist/src');

foreach (PhabricatorEnv::getEnvConfig('load-libraries') as $library) {
  phutil_load_library($library);
}

PhutilErrorHandler::initialize();
PhabricatorEventEngine::initialize();

$tz = PhabricatorEnv::getEnvConfig('phabricator.timezone');
if ($tz) {
  date_default_timezone_set($tz);
}

$translation = PhabricatorEnv::newObjectFromConfig('translation.provider');
PhutilTranslator::getInstance()
  ->setLanguage($translation->getLanguage())
  ->addTranslations($translation->getTranslations());

// Append any paths to $PATH if we need to.
$paths = PhabricatorEnv::getEnvConfig('environment.append-paths');
if (!empty($paths)) {
  $current_env_path = getenv('PATH');
  $new_env_paths = implode(PATH_SEPARATOR, $paths);
  putenv('PATH='.$current_env_path.PATH_SEPARATOR.$new_env_paths);
}
