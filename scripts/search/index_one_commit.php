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

if (empty($argv[1])) {
  echo "usage: index_one_commit.php <commit_name>\n";
  die(1);
}

$commit = isset($argv[1]) ? $argv[1] : null;
if (!$commit) {
  throw new Exception("Provide a commit to index!");
}
$matches = null;
if (!preg_match('/r([A-Z]+)([a-z0-9]+)/', $commit, $matches)) {
  throw new Exception("Can't parse commit identifier!");
}
$repo = id(new PhabricatorRepository())->loadOneWhere(
  'callsign = %s',
  $matches[1]);
if (!$repo) {
  throw new Exception("Unknown repository!");
}

$commit = id(new PhabricatorRepositoryCommit())->loadOneWhere(
  'repositoryID = %d AND commitIdentifier = %s',
  $repo->getID(),
  $matches[2]);
if (!$commit) {
  throw new Exception('Unknown commit.');
}

PhabricatorSearchCommitIndexer::indexCommit($commit);
echo "Done.\n";
