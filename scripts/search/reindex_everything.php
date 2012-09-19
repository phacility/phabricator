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

// TODO: Get rid of this script eventually, once this stuff is better-formalized
// in Timeline consumers.

echo "Reindexing revisions...\n";
$revs = new LiskMigrationIterator(new DifferentialRevision());
foreach ($revs as $rev) {
  PhabricatorSearchDifferentialIndexer::indexRevision($rev);
  echo '.';
}
echo "\n";

echo "Reindexing commits...\n";
$commits = new LiskMigrationIterator(new PhabricatorRepositoryCommit());
foreach ($commits as $commit) {
  PhabricatorSearchCommitIndexer::indexCommit($commit);
  echo '.';
}
echo "\n";

echo "Reindexing tasks...\n";
$tasks = new LiskMigrationIterator(new ManiphestTask());
foreach ($tasks as $task) {
  PhabricatorSearchManiphestIndexer::indexTask($task);
  echo '.';
}
echo "\n";

include dirname(__FILE__).'/reindex_all_users.php';
