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

// TODO: Get rid of this script eventually, once this stuff is better-formalized
// in Timeline consumers.

echo "Loading revisions...\n";
$revs = id(new DifferentialRevision())->loadAll();
$count = count($revs);
echo "Reindexing {$count} revisions";
foreach ($revs as $rev) {
  PhabricatorSearchDifferentialIndexer::indexRevision($rev);
  echo '.';
}
echo "\n";

echo "Loading commits...\n";
$commits = id(new PhabricatorRepositoryCommit())->loadAll();
$count = count($commits);
echo "Reindexing {$count} commits";
foreach ($commits as $commit) {
  PhabricatorSearchCommitIndexer::indexCommit($commit);
  echo '.';
}
echo "\n";

echo "Loading tasks...\n";
$tasks = id(new ManiphestTask())->loadAll();
$count = count($tasks);
echo "Reindexing {$count} tasks";
foreach ($tasks as $task) {
  PhabricatorSearchManiphestIndexer::indexTask($task);
  echo '.';
}
echo "\n";
echo "Done.\n";

