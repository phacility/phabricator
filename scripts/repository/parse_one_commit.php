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

if (empty($argv[1])) {
  echo "usage: parse_one_commit.php <commit_name>\n";
  die(1);
}

$commit = isset($argv[1]) ? $argv[1] : null;
if (!$commit) {
  throw new Exception("Provide a commit to parse!");
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

$workers = array();

$spec = array(
  'commitID'  => $commit->getID(),
  'only'      => true,
);

switch ($repo->getVersionControlSystem()) {
  case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
    $workers[] = new PhabricatorRepositoryGitCommitMessageParserWorker(
      $spec);
    $workers[] = new PhabricatorRepositoryGitCommitChangeParserWorker(
      $spec);
    break;
  case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
    $workers[] = new PhabricatorRepositorySvnCommitMessageParserWorker(
      $spec);
    $workers[] = new PhabricatorRepositorySvnCommitChangeParserWorker(
      $spec);
    break;
  default:
    throw new Exception("Unknown repository type!");
}

ExecFuture::pushEchoMode(true);

foreach ($workers as $worker) {
  echo "Running ".get_class($worker)."...\n";
  $worker->doWork();
}

echo "Done.\n";

