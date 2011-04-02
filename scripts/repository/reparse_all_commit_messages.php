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

if (empty($argv[1])) {
  echo "usage: reparse_all_commit_messages.php all\n".
       "       reparse_all_commit_messages.php <repository_callsign>\n";
  exit(1);
}


echo phutil_console_format(
  'This script will queue tasks to reparse every commit message known to '.
  'Diffusion. Once the tasks have been inserted, you need to start '.
  'Taskmaster daemons to execute them.');

$ok = phutil_console_confirm('Do you want to continue?');
if (!$ok) {
  die(1);
}

if ($argv[1] == 'all') {
  echo "Loading all repositories...\n";
  $repositories = id(new PhabricatorRepository())->loadAll();
  echo "Loading all commits...\n";
  $commits = id(new PhabricatorRepositoryCommit())->loadAll();
} else {
  $callsign = $argv[1];
  echo "Loading '{$callsign}' repository...\n";
  $repository = id(new PhabricatorRepository())->loadOneWhere(
    'callsign = %s',
    $argv[1]);
  if (!$repository) {
    throw new Exception("No such repository exists!");
  }
  $repositories = array(
    $repository->getID() => $repository,
  );
  echo "Loading commits in '{$callsign}' repository...\n";
  $commits = id(new PhabricatorRepositoryCommit())->loadAllWhere(
    'repositoryID = %d',
    $repository->getID());
}

echo "Inserting tasks for ".count($commits)." commits";
foreach ($commits as $commit) {
  echo ".";
  $id = $commit->getID();
  $repo = idx($repositories, $commit->getRepositoryID());
  if (!$repo) {
    echo "\nWarning: Commit #{$id} has an invalid repository ID.\n";
  }

  switch ($repo->getVersionControlSystem()) {
    case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
      $task_class = 'PhabricatorRepositoryGitCommitMessageParserWorker';
      break;
    case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
      $task_class = 'PhabricatorRepositorySvnCommitMessageParserWorker';
      break;
    default:
      throw new Exception("Unknown repository type!");
  }

  $task = new PhabricatorWorkerTask();
  $task->setTaskClass($task_class);
  $task->setData(
    array(
      'commitID'  => $commit->getID(),
      'only'      => true,
    ));
  $task->save();
}
echo "\nDone.\n";
