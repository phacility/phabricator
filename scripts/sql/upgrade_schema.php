#!/usr/bin/env php
<?php

/*
 * Copyright 2012 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

$root = dirname(dirname(dirname(__FILE__)));
require_once $root.'/scripts/__init_script__.php';

phutil_require_module('phutil', 'console');
phutil_require_module('phabricator', 'infrastructure/setup/sql');

define('SCHEMA_VERSION_TABLE_NAME', 'schema_version');

// TODO: getopt() is super terrible, move to something less terrible.
$options = getopt('fhdv:u:p:m:') + array(
  'v' => null, // Upgrade from specific version
  'u' => null, // Override MySQL User
  'p' => null, // Override MySQL Pass
  'm' => null, // Specify max version to upgrade to
);

foreach (array('h', 'f', 'd') as $key) {
  // By default, these keys are set to 'false' to indicate that the flag was
  // passed.
  if (array_key_exists($key, $options)) {
    $options[$key] = true;
  }
}

if (!empty($options['h']) || ($options['v'] && !is_numeric($options['v']))
    || ($options['m'] && !is_numeric($options['m']))) {
  usage();
}

if (empty($options['f']) && empty($options['d'])) {
  echo phutil_console_wrap(
    "Before running this script, you should take down the Phabricator web ".
    "interface and stop any running Phabricator daemons.");

  if (!phutil_console_confirm('Are you ready to continue?')) {
    echo "Cancelled.\n";
    exit(1);
  }
}

// Use always the version from the commandline if it is defined
$next_version = isset($options['v']) ? (int)$options['v'] : null;
$max_version = isset($options['m']) ? (int)$options['m'] : null;

$conf = DatabaseConfigurationProvider::getConfiguration();

if ($options['u']) {
  $conn_user = $options['u'];
  $conn_pass = $options['p'];
} else {
  $conn_user = $conf->getUser();
  $conn_pass = $conf->getPassword();
}
$conn_host = $conf->getHost();

// Split out port information, since the command-line client requires a
// separate flag for the port.
$uri = new PhutilURI('mysql://'.$conn_host);
if ($uri->getPort()) {
  $conn_port = $uri->getPort();
  $conn_bare_hostname = $uri->getDomain();
} else {
  $conn_port = null;
  $conn_bare_hostname = $conn_host;
}

$conn = new AphrontMySQLDatabaseConnection(
  array(
    'user'      => $conn_user,
    'pass'      => $conn_pass,
    'host'      => $conn_host,
    'database'  => null,
  ));

try {

  $create_sql = <<<END
  CREATE DATABASE IF NOT EXISTS `phabricator_meta_data`;
END;
  queryfx($conn, $create_sql);

  $create_sql = <<<END
  CREATE TABLE IF NOT EXISTS phabricator_meta_data.`schema_version` (
    `version` INTEGER not null
  );
END;
  queryfx($conn, $create_sql);

  // Get the version only if commandline argument wasn't given
  if ($next_version === null) {
    $version = queryfx_one(
      $conn,
      'SELECT * FROM phabricator_meta_data.%T',
      SCHEMA_VERSION_TABLE_NAME);

    if (!$version) {
      print "*** No version information in the database ***\n";
      print "*** Give the first patch version which to  ***\n";
      print "*** apply as the command line argument     ***\n";
      exit(-1);
    }

    $next_version = $version['version'] + 1;
  }

  $patches = PhabricatorSQLPatchList::getPatchList();

  $patch_applied = false;
  foreach ($patches as $patch) {
    if ($patch['version'] < $next_version) {
      continue;
    }

    if ($max_version && $patch['version'] > $max_version) {
      continue;
    }

    $short_name = basename($patch['path']);
    print "Applying patch {$short_name}...\n";

    if (!empty($options['d'])) {
      $patch_applied = true;
      continue;
    }

    if ($conn_port) {
      $port = '--port='.(int)$conn_port;
    } else {
      $port = null;
    }

    if (preg_match('/\.php$/', $patch['path'])) {
      $schema_conn = $conn;
      require_once $patch['path'];
    } else {
      list($stdout, $stderr) = execx(
        "mysql --user=%s --password=%s --host=%s {$port} ".
        "--default-character-set=utf8 < %s",
        $conn_user,
        $conn_pass,
        $conn_bare_hostname,
        $patch['path']);

      if ($stderr) {
        print $stderr;
        exit(-1);
      }
    }

    // Patch was successful, update the db with the latest applied patch version
    // 'DELETE' and 'INSERT' instead of update, because the table might be empty
    queryfx(
      $conn,
      'DELETE FROM phabricator_meta_data.%T',
      SCHEMA_VERSION_TABLE_NAME);
    queryfx(
      $conn,
      'INSERT INTO phabricator_meta_data.%T VALUES (%d)',
      SCHEMA_VERSION_TABLE_NAME,
      $patch['version']);

    $patch_applied = true;
  }

  if (!$patch_applied) {
    print "Your database is already up-to-date.\n";
  }

} catch (AphrontQueryAccessDeniedException $ex) {
  echo
    "ACCESS DENIED\n".
    "The user '{$conn_user}' does not have sufficient MySQL privileges to\n".
    "execute the schema upgrade. Use the -u and -p flags to run as a user\n".
    "with more privileges (e.g., root).".
    "\n\n".
    "EXCEPTION:\n".
    $ex->getMessage().
    "\n\n";
  exit(1);
}

function usage() {
  echo
    "usage: upgrade_schema.php [-v version] [-u user -p pass] [-f] [-h]".
    "\n\n".
    "Run 'upgrade_schema.php -u root -p hunter2' to override the configured ".
    "default user.\n".
    "Run 'upgrade_schema.php -v 12' to apply all patches starting from ".
    "version 12. It is very unlikely you need to do this.\n".
    "Run 'upgrade_schema.php -m 110' to apply all patches up to and ".
    "including version 110 (but nothing past).\n".
    "Use the -f flag to upgrade noninteractively, without prompting.\n".
    "Use the -d flag to do a dry run - patches that would be applied ".
    "will be listed, but not applied.\n".
    "Use the -h flag to show this help.\n";
  exit(1);
}

