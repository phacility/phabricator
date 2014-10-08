#!/usr/bin/env php
<?php

$root = dirname(dirname(dirname(__FILE__)));
require_once $root.'/scripts/__init_script__.php';

$args = new PhutilArgumentParser($argv);
$args->setTagline('manage Phabricator storage and schemata');
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
        'help'    => 'Do not prompt before performing dangerous operations.',
      ),
      array(
        'name'    => 'user',
        'short'   => 'u',
        'param'   => 'username',
        'default' => $default_user,
        'help'    => "Connect with __username__ instead of the configured ".
                     "default ('{$default_user}').",
      ),
      array(
        'name'    => 'password',
        'short'   => 'p',
        'param'   => 'password',
        'help'    => 'Use __password__ instead of the configured default.',
      ),
      array(
        'name'    => 'namespace',
        'param'   => 'name',
        'default' => $default_namespace,
        'help'    => "Use namespace __namespace__ instead of the configured ".
                     "default ('{$default_namespace}'). This is an advanced ".
                     "feature used by unit tests; you should not normally ".
                     "use this flag.",
      ),
      array(
        'name'  => 'dryrun',
        'help'  => 'Do not actually change anything, just show what would be '.
                   'changed.',
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
    pht(
      "**MySQL Credentials Not Configured**\n\n".
      "Unable to connect to MySQL using the configured credentials. ".
      "You must configure standard credentials before you can upgrade ".
      "storage. Run these commands to set up credentials:\n".
      "\n".
      "  phabricator/ $ ./bin/config set mysql.host __host__\n".
      "  phabricator/ $ ./bin/config set mysql.user __username__\n".
      "  phabricator/ $ ./bin/config set mysql.pass __password__\n".
      "\n".
      "These standard credentials are separate from any administrative ".
      "credentials provided to this command with __--user__ or ".
      "__--password__, and must be configured correctly before you can ".
      "proceed.\n".
      "\n".
      "**Raw MySQL Error**: %s\n",
      $ex->getMessage()));

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

try {
  queryfx(
    $api->getConn(null),
    'SELECT 1');
} catch (AphrontQueryException $ex) {
  $message = phutil_console_format(
    pht(
      "**Bad Administrative Credentials**\n\n".
      "Unable to connnect to MySQL using the administrative credentials ".
      "provided with the __--user__ and __--password__ flags. Check that ".
      "you have entered them correctly.\n".
      "\n".
      "**Raw MySQL Error**: %s\n",
      $ex->getMessage()));

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
