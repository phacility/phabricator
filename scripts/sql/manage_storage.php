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
$api->setPassword($password);
$api->setNamespace($args->getArg('namespace'));

try {
  queryfx(
    $api->getConn(null),
    'SELECT 1');
} catch (AphrontQueryException $ex) {
  echo phutil_console_format(
    "**%s**: %s\n",
    'Unable To Connect',
    $ex->getMessage());
  exit(1);
}

$workflows = array(
  new PhabricatorStorageManagementDatabasesWorkflow(),
  new PhabricatorStorageManagementDestroyWorkflow(),
  new PhabricatorStorageManagementDumpWorkflow(),
  new PhabricatorStorageManagementStatusWorkflow(),
  new PhabricatorStorageManagementUpgradeWorkflow(),
);

$patches = PhabricatorSQLPatchList::buildAllPatches();

foreach ($workflows as $workflow) {
  $workflow->setAPI($api);
  $workflow->setPatches($patches);
}

$workflows[] = new PhutilHelpArgumentWorkflow();

$args->parseWorkflows($workflows);
