#!/usr/bin/env php
<?php

$root = dirname(dirname(dirname(__FILE__)));
require_once $root.'/scripts/init/init-setup.php';

$args = new PhutilArgumentParser($argv);
$args->setTagline(pht('manage storage and schemata'));
$args->setSynopsis(<<<EOHELP
**storage** __workflow__ [__options__]
Manage database storage and schema versioning.

**storage** upgrade
Initialize or upgrade storage.

**storage** upgrade --user __root__ --password __hunter2__
Use administrative credentials for schema changes.
EOHELP
);
$args->parseStandardArguments();

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
        'name' => 'host',
        'param' => 'hostname',
        'help' => pht(
          'Operate on the database server identified by __hostname__.'),
      ),
      array(
        'name' => 'ref',
        'param' => 'ref',
        'help' => pht(
          'Operate on the database identified by __ref__.'),
      ),
      array(
        'name'    => 'user',
        'short'   => 'u',
        'param'   => 'username',
        'help'    => pht(
          'Connect with __username__ instead of the configured default.'),
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
        'name'    => 'dryrun',
        'help'    => pht(
          'Do not actually change anything, just show what would be changed.'),
      ),
      array(
        'name'    => 'disable-utf8mb4',
        'help'    => pht(
          'Disable %s, even if the database supports it. This is an '.
          'advanced feature used for testing internal changes; you '.
          'should not normally use this flag.',
          'utf8mb4'),
      ),
    ));
} catch (PhutilArgumentUsageException $ex) {
  $args->printUsageException($ex);
  exit(77);
}

// First, test that the Phabricator configuration is set up correctly. After
// we know this works we'll test any administrative credentials specifically.

$refs = PhabricatorDatabaseRef::getActiveDatabaseRefs();
if (!$refs) {
  throw new PhutilArgumentUsageException(
    pht('No databases are configured.'));
}

$host = $args->getArg('host');
$ref_key = $args->getArg('ref');
if (($host !== null) || ($ref_key !== null)) {
  if ($host && $ref_key) {
    throw new PhutilArgumentUsageException(
      pht(
        'Use "--host" or "--ref" to select a database, but not both.'));
  }

  $refs = PhabricatorDatabaseRef::getActiveDatabaseRefs();

  $possible_refs = array();
  foreach ($refs as $possible_ref) {
    if ($host && ($possible_ref->getHost() == $host)) {
      $possible_refs[] = $possible_ref;
      break;
    }
    if ($ref_key && ($possible_ref->getRefKey() == $ref_key)) {
      $possible_refs[] = $possible_ref;
      break;
    }
  }

  if (!$possible_refs) {
    if ($host) {
      throw new PhutilArgumentUsageException(
        pht(
          'There is no configured database on host "%s". This command can '.
          'only interact with configured databases.',
          $host));
    } else {
      throw new PhutilArgumentUsageException(
        pht(
          'There is no configured database with ref "%s". This command can '.
          'only interact with configured databases.',
          $ref_key));
    }
  }

  if (count($possible_refs) > 1) {
    throw new PhutilArgumentUsageException(
      pht(
        'Host "%s" identifies more than one database. Use "--ref" to select '.
        'a specific database.',
        $host));
  }

  $refs = $possible_refs;
}

$apis = array();
foreach ($refs as $ref) {
  $default_user = $ref->getUser();
  $default_host = $ref->getHost();
  $default_port = $ref->getPort();

  $test_api = id(new PhabricatorStorageManagementAPI())
    ->setUser($default_user)
    ->setHost($default_host)
    ->setPort($default_port)
    ->setPassword($ref->getPass())
    ->setNamespace($args->getArg('namespace'));

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
      "  $ ./bin/config set mysql.host __host__\n".
      "  $ ./bin/config set mysql.user __username__\n".
      "  $ ./bin/config set mysql.pass __password__",
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
    $password = $ref->getPass();
  } else {
    // Put this in a PhutilOpaqueEnvelope.
    $password = new PhutilOpaqueEnvelope($args->getArg('password'));
    PhabricatorEnv::overrideConfig('mysql.pass', $args->getArg('password'));
  }

  $selected_user = $args->getArg('user');
  if ($selected_user === null) {
    $selected_user = $default_user;
  }

  $api = id(new PhabricatorStorageManagementAPI())
    ->setUser($selected_user)
    ->setHost($default_host)
    ->setPort($default_port)
    ->setPassword($password)
    ->setNamespace($args->getArg('namespace'))
    ->setDisableUTF8MB4($args->getArg('disable-utf8mb4'));
  PhabricatorEnv::overrideConfig('mysql.user', $api->getUser());

  $ref->setUser($selected_user);
  $ref->setPass($password);

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

  $api->setRef($ref);
  $apis[] = $api;
}

$workflows = id(new PhutilClassMapQuery())
  ->setAncestorClass('PhabricatorStorageManagementWorkflow')
  ->execute();

$patches = PhabricatorSQLPatchList::buildAllPatches();

foreach ($workflows as $workflow) {
  $workflow->setAPIs($apis);
  $workflow->setPatches($patches);
}

$workflows[] = new PhutilHelpArgumentWorkflow();

$args->parseWorkflows($workflows);
