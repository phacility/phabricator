#!/usr/bin/env php
<?php

/*
 * Copyright 2011 Facebook, Inc.
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
require_once $root.'/scripts/__init_env__.php';

phutil_require_module('phutil', 'console');

define('SCHEMA_VERSION_TABLE_NAME', 'schema_version');

if (isset($argv[1]) && !is_numeric($argv[1])) {
  print
    "USAGE: ./update_schema.php [first_patch_version]\n\n".
    "run './update_schema.php 12' to apply all patches starting from ".
    "version 12.\n".
    "run './update_schema.php' to apply all patches that are new since\n".
    "the last time this script was run\n\n";
  exit(0);
}

echo phutil_console_wrap(
  "Before running this script, you should take down the Phabricator web ".
  "interface and stop any running Phabricator daemons.");

if (!phutil_console_confirm('Are you ready to continue?')) {
  echo "Cancelled.\n";
  exit(1);
}

// Use always the version from the commandline if it is defined
$next_version = isset($argv[1]) ? (int)$argv[1] : null;

// Dummy class needed for creating our database
class DummyUser extends PhabricatorLiskDAO {
  public function getApplicationName() {
    return 'user';
  }
}

// Class needed for setting up the actual SQL connection
class PhabricatorSchemaVersion extends PhabricatorLiskDAO {
  public function getApplicationName() {
    return 'meta_data';
  }
}

// Connect to 'phabricator_user' db first to create our db
$conn = id(new DummyUser())->establishConnection('w');
$create_sql = <<<END
CREATE DATABASE IF NOT EXISTS `phabricator_meta_data`;
END;
queryfx($conn, $create_sql);

// 'phabricator_meta_data' database exists, let's connect to it now
$conn = id(new PhabricatorSchemaVersion())->establishConnection('w');
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
    'SELECT * FROM %T',
    SCHEMA_VERSION_TABLE_NAME);

  if (!$version) {
    print "*** No version information in the database ***\n";
    print "*** Give the first patch version which to  ***\n";
    print "*** apply as the command line argument     ***\n";
    exit(-1);
  }

  $next_version = $version['version'] + 1;
}

// Find the patch files
$patches_dir = $root.'/resources/sql/patches/';
$finder = id(new FileFinder($patches_dir))
  ->withSuffix('sql');
$results = $finder->find();

$patches = array();
foreach ($results as $r) {
  $matches = array();
  if (preg_match('/(\d+)\..*\.sql$/', $r, $matches)) {
    $patches[] = array('version' => (int)$matches[1],
                       'file' => $r);
  } else {
    print
      "*** WARNING : File {$r} does not follow the normal naming ".
      "convention. ***\n";
  }
}

// Files are in some 'random' order returned by the operating system
// We need to apply them in proper order
$patches = isort($patches, 'version');

$patch_applied = false;
foreach ($patches as $patch) {
  if ($patch['version'] < $next_version) {
    continue;
  }

  print "Applying patch {$patch['file']}\n";

  $path = Filesystem::resolvePath($patches_dir.$patch['file']);

  $user = PhabricatorEnv::getEnvConfig('mysql.user');
  $pass = PhabricatorEnv::getEnvConfig('mysql.pass');
  $host = PhabricatorEnv::getEnvConfig('mysql.host');

  list($stdout, $stderr) = execx(
    "mysql --user=%s --password=%s --host=%s < %s",
    $user, $pass, $host, $path);

  if ($stderr) {
    print $stderr;
    exit(-1);
  }

  // Patch was successful, update the db with the latest applied patch version
  // 'DELETE' and 'INSERT' instead of update, because the table might be empty
  queryfx($conn, 'DELETE FROM %T', SCHEMA_VERSION_TABLE_NAME);
  queryfx(
    $conn,
    'INSERT INTO %T values (%d)',
    SCHEMA_VERSION_TABLE_NAME,
    $patch['version']);

  $patch_applied = true;
}

if (!$patch_applied) {
  print "Your database is already up-to-date.\n";
}
