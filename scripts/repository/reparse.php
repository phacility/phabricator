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

phutil_require_module('phutil', 'console');

$is_all = false;
$reparse_message = false;
$reparse_change = false;
$reparse_herald = false;
$reparse_what = false;

$args = array_slice($argv, 1);
foreach ($args as $arg) {
  if (!strncmp($arg, '--', 2)) {
    $flag = substr($arg, 2);
    switch ($flag) {
      case 'all':
        $is_all = true;
        break;
      case 'message':
      case 'messages':
        $reparse_message = true;
        break;
      case 'change':
      case 'changes':
        $reparse_change = true;
        break;
      case 'herald':
        $reparse_herald = true;
        break;
      case 'trace':
        PhutilServiceProfiler::installEchoListener();
        break;
      case 'help':
        help();
        break;
      default:
        usage("Unknown flag '{$arg}'.");
    }
  } else {
    if ($reparse_what) {
      usage("Specify exactly one thing to reparse.");
    }
    $reparse_what = $arg;
  }
}

if (!$reparse_what) {
  usage("Specify a commit or repository to reparse.");
}
if (!$reparse_message && !$reparse_change && !$reparse_herald) {
  usage("Specify what information to reparse with --message, --change, and/or ".
        "--herald.");
}

$commits = array();
if ($is_all) {
  $repository = id(new PhabricatorRepository())->loadOneWhere(
    'callsign = %s OR phid = %s',
    $reparse_what,
    $reparse_what);
  if (!$repository) {
    throw new Exception("Unknown repository '{$reparse_what}'!");
  }
  $commits = id(new PhabricatorRepositoryCommit())->loadAllWhere(
    'repositoryID = %d',
    $repository->getID());
  if (!$commits) {
    throw new Exception("No commits have been discovered in that repository!");
  }
  $callsign = $repository->getCallsign();
} else {
  $matches = null;
  if (!preg_match('/r([A-Z]+)([a-z0-9]+)/', $reparse_what, $matches)) {
    throw new Exception("Can't parse commit identifier!");
  }
  $callsign = $matches[1];
  $commit_identifier = $matches[2];
  $repository = id(new PhabricatorRepository())->loadOneWhere(
    'callsign = %s',
    $callsign);
  if (!$repository) {
    throw new Exception("No repository with callsign '{$callsign}'!");
  }
  $commit = id(new PhabricatorRepositoryCommit())->loadOneWhere(
    'repositoryID = %d AND commitIdentifier = %s',
    $repository->getID(),
    $commit_identifier);
  if (!$commit) {
    throw new Exception(
      "No matching commit '{$commit_identifier}' in repository '{$callsign}'. ".
      "(For git and mercurial repositories, you must specify the entire ".
      "commit hash.)");
  }
  $commits = array($commit);
}

if ($is_all) {
  echo phutil_console_format(
    '**NOTE**: This script will queue tasks to reparse the data. Once the '.
    'tasks have been queued, you need to run Taskmaster daemons to execute '.
    'them.');
  echo "\n\n";
  echo "QUEUEING TASKS (".number_format(count($commits))." Commits):\n";
}

$tasks = array();
foreach ($commits as $commit) {
  $classes = array();
  switch ($repository->getVersionControlSystem()) {
    case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
      if ($reparse_message) {
        $classes[] = 'PhabricatorRepositoryGitCommitMessageParserWorker';
      }
      if ($reparse_change) {
        $classes[] = 'PhabricatorRepositoryGitCommitChangeParserWorker';
      }
      break;
    case PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL:
      if ($reparse_message) {
        $classes[] = 'PhabricatorRepositoryMercurialCommitMessageParserWorker';
      }
      if ($reparse_change) {
        $classes[] = 'PhabricatorRepositoryMercurialCommitChangeParserWorker';
      }
      break;
    case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
      if ($reparse_message) {
        $classes[] = 'PhabricatorRepositorySvnCommitMessageParserWorker';
      }
      if ($reparse_change) {
        $classes[] = 'PhabricatorRepositorySvnCommitChangeParserWorker';
      }
      break;
  }

  if ($reparse_herald) {
    $classes[] = 'PhabricatorRepositoryCommitHeraldWorker';
  }

  $spec = array(
    'commitID'  => $commit->getID(),
    'only'      => true,
  );

  if ($is_all) {
    foreach ($classes as $class) {
      $task = new PhabricatorWorkerTask();
      $task->setTaskClass($class);
      $task->setData($spec);
      $task->save();

      $commit_name = 'r'.$callsign.$commit->getCommitIdentifier();
      echo "  Queued '{$class}' for commit '{$commit_name}'.\n";
    }
  } else {
    foreach ($classes as $class) {
      $worker = newv($class, array($spec));
      echo "Running '{$class}'...\n";
      $worker->doWork();
    }
  }
}

echo "\nDone.\n";

function usage($message) {
  echo "Usage Error: {$message}";
  echo "\n\n";
  echo "Run 'reparse.php --help' for detailed help.\n";
  exit(1);
}

function help() {
  $help = <<<EOHELP
**SUMMARY**

    **reparse.php** __what__ __which_parts__ [--trace]

    Rerun the Diffusion parser on specific commits and repositories. Mostly
    useful for debugging changes to Diffusion.

    __what__: what to reparse

        __commit__
            Reparse one commit. This mode will reparse the commit in-process.

        --all __repository_callsign__
        --all __repository_phid__
            Reparse all commits in the specified repository. These modes queue
            parsers into the task queue, you must run taskmasters to actually
            do the parses them.

    __which_parts__: which parts of the thing to reparse

        __--message__
            Reparse commit messages.

        __--change__
            Reparse changes.

        __--herald__
            Reevaluate Herald rules (may send huge amounts of email!)

    __--trace__: run with debug tracing
    __--help__: show this help

**EXAMPLES**

  reparse.php rX123 --change       # Reparse change for "rX123".
  reparse.php --all E --message    # Reparse all messages in "E" repository.

EOHELP;
  echo phutil_console_format($help);
  exit(1);
}
