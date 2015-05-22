#!/usr/bin/env php
<?php

$root = dirname(dirname(dirname(__FILE__)));
require_once $root.'/scripts/__init_script__.php';

$args = new PhutilArgumentParser($argv);
$args->setTagline(pht('manage Phabricator storage and schemata'));
$args->setSynopsis(<<<EOHELP
**storage** __workflow__ [__options__]
Manage Phabricator database storage and schema versioning.

**storage** upgrade
Initialize or upgrade Phabricator storage.

**storage** upgrade --user __root__ --password __hunter2__
Use administrative credentials for schema changes.
EOHELP
);
$args->parseStandardArguments();

$conf = PhabricatorEnv::newObjectFromConfig(
  'mysql.configuration-provider',
  array($dao = null, 'w'));

$default_user       = $conf->getUser();
$default_host       = $conf->getHost();
$default_port       = $conf->getPort();
$default_namespace  = PhabricatorLiskDAO::getDefaultStorageNamespace();

try {
  $args->parsePartial(
    array(
      array(
        'name'    => 'force',
        'short'   => 'f',
        'help'    => pht(
          'Do not prompt before performing dangerous operations.'),
      ),
      array(
        'name'    => 'user',
        'short'   => 'u',
        'param'   => 'username',
        'default' => $default_user,
        'help'    => pht(
          "Connect with __username__ instead of the configured default ('%s').",
          $default_user),
      ),
      array(
        'name'    => 'password',
        'short'   => 'p',
        'param'   => 'password',
        'help'    => pht('Use __password__ instead of the configured default.'),
      ),
      array(
        'name'    => 'namespace',
        'param'   => 'name',
        'default' => $default_namespace,
        'help'    => pht(
          "Use namespace __namespace__ instead of the configured ".
          "default ('%s'). This is an advanced feature used by unit tests; ".
          "you should not normally use this flag.",
          $default_namespace),
      ),
      array(
        'name'  => 'dryrun',
        'help'  => pht(
          'Do not actually change anything, just show what would be changed.'),
      ),
      array(
        'name' => 'disable-utf8mb4',
        'help' => pht(
          'Disable utf8mb4, even if the database supports it. This is an '.
          'advanced feature used for testing changes to Phabricator; you '.
          'should not normally use this flag.'),
      ),
    ));
} catch (PhutilArgumentUsageException $ex) {
  $args->printUsageException($ex);
  exit(77);
}

// First, test that the Phabricator configuration is set up correctly. After
// we know this works we'll test any administrative credentials specifically.

$test_api = new PhabricatorStorageManagementAPI();
$test_api->setUser($default_user);
$test_api->setHost($default_host);
$test_api->setPort($default_port);
$test_api->setPassword($conf->getPassword());
$test_api->setNamespace($args->getArg('namespace'));

try {
  queryfx(
    $test_api->getConn(null),
    'SELECT 1');
} catch (AphrontQueryException $ex) {
  $message = phutil_console_format(
    "**%s**\n\n%s\n\n%s\n\n%s\n\n**%s**: %s\n",
    pht('MySQL Credentials Not Configured'),
    pht(
      'Unable to connect to MySQL using the configured credentials. '.
      'You must configure standard credentials before you can upgrade '.
      'storage. Run these commands to set up credentials:'),
    "  phabricator/ $ ./bin/config set mysql.host __host__\n".
    "  phabricator/ $ ./bin/config set mysql.user __username__\n".
    "  phabricator/ $ ./bin/config set mysql.pass __password__",
    pht(
      'These standard credentials are separate from any administrative '.
      'credentials provided to this command with __%s__ or '.
      '__%s__, and must be configured correctly before you can proceed.',
      '--user',
      '--password'),
    pht('Raw MySQL Error'),
    $ex->getMessage());

  echo phutil_console_wrap($message);

  exit(1);
}


if ($args->getArg('password') === null) {
  // This is already a PhutilOpaqueEnvelope.
  $password = $conf->getPassword();
} else {
  // Put this in a PhutilOpaqueEnvelope.
  $password = new PhutilOpaqueEnvelope($args->getArg('password'));
  PhabricatorEnv::overrideConfig('mysql.pass', $args->getArg('password'));
}

$api = new PhabricatorStorageManagementAPI();
$api->setUser($args->getArg('user'));
PhabricatorEnv::overrideConfig('mysql.user', $args->getArg('user'));
$api->setHost($default_host);
$api->setPort($default_port);
$api->setPassword($password);
$api->setNamespace($args->getArg('namespace'));
$api->setDisableUTF8MB4($args->getArg('disable-utf8mb4'));

try {
  queryfx(
    $api->getConn(null),
    'SELECT 1');
} catch (AphrontQueryException $ex) {
  $message = phutil_console_format(
    "**%s**\n\n%s\n\n**%s**: %s\n",
    pht('Bad Administrative Credentials'),
    pht(
      'Unable to connect to MySQL using the administrative credentials '.
      'provided with the __%s__ and __%s__ flags. Check that '.
      'you have entered them correctly.',
      '--user',
      '--password'),
    pht('Raw MySQL Error'),
    $ex->getMessage());

  echo phutil_console_wrap($message);

  exit(1);
}

$workflows = id(new PhutilSymbolLoader())
  ->setAncestorClass('PhabricatorStorageManagementWorkflow')
  ->loadObjects();

$patches = PhabricatorSQLPatchList::buildAllPatches();

foreach ($workflows as $workflow) {
  $workflow->setAPI($api);
  $workflow->setPatches($patches);
}

$workflows[] = new PhutilHelpArgumentWorkflow();

$args->parseWorkflows($workflows);
