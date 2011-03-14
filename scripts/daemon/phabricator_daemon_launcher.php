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

$phd_dir = PhabricatorEnv::getEnvConfig('phd.pid-directory');
$pid_dir = $phd_dir.'/pid';

switch (isset($argv[1]) ? $argv[1] : 'help') {
  case 'list':
    phutil_require_module('phutil', 'console');

    $loader = new PhutilSymbolLoader();
    $symbols = $loader
      ->setAncestorClass('PhutilDaemon')
      ->selectSymbolsWithoutLoading();

    $symbols = igroup($symbols, 'library');
    foreach ($symbols as $library => $symbol_list) {
      echo phutil_console_format("Daemons in library __%s__:\n", $library);
      foreach ($symbol_list as $symbol) {
        echo "  ".$symbol['name']."\n";
      }
      echo "\n";
    }

    break;

  case 'status':
    $pid_descs = Filesystem::listDirectory($pid_dir);
    if (!$pid_descs) {
      echo "There are no running Phabricator daemons.\n";
    } else {
      printf(
        "%-5s\t%-24s\t%s\n",
        "PID",
        "Started",
        "Daemon");
      foreach ($pid_descs as $pid_file) {
        $data = Filesystem::readFile($pid_dir.'/'.$pid_file);
        $data = json_decode($data, true);

        $pid = idx($data, 'pid', '?');
        $name = idx($data, 'name', '?');
        $since = idx($data, 'start')
          ? date('M j Y, g:i:s A', $data['start'])
          : '?';

        printf(
          "%5s\t%-24s\t%s\n",
          $pid,
          $since,
          $name);
      }
    }

    break;

  case 'launch':
    phutil_require_module('phutil', 'moduleutils');

    $daemon = idx($argv, 2);
    if (!$daemon) {
      throw new Exception("Daemon name required!");
    }

    $n = 1;
    if (is_numeric($daemon)) {
      $n = $daemon;
      if ($n < 1) {
        throw new Exception("Count must be at least 1!");
      }
      $daemon = idx($argv, 3);
      if (!$daemon) {
        throw new Exception("Daemon name required!");
      }
    }

    $loader = new PhutilSymbolLoader();
    $symbols = $loader
      ->setAncestorClass('PhutilDaemon')
      ->selectSymbolsWithoutLoading();

    $symbols = ipull($symbols, 'name');
    $match = array();
    foreach ($symbols as $symbol) {
      if (stripos($symbol, $daemon) !== false) {
        if (strtolower($symbol) == strtolower($daemon)) {
          $match = array($symbol);
          break;
        } else {
          $match[] = $symbol;
        }
      }
    }

    if (count($match) == 0) {
      throw new Exception(
        "No daemons match! Use 'phd list' for a list of daemons.");
    } else if (count($match) > 1) {
      throw new Exception(
        "Which of these daemons did you mean?\n".
        "    ".implode("\n    ", $match));
    } else {
      $daemon = reset($match);
    }

    $libphutil_root = dirname(phutil_get_library_root('phutil'));
    $launch_daemon = $libphutil_root.'/scripts/daemon/';

    // TODO: This should be a much better user experience.
    Filesystem::assertExists($pid_dir);
    Filesystem::assertIsDirectory($pid_dir);
    Filesystem::assertWritable($pid_dir);

    echo "Starting {$n} x {$daemon}";
    for ($ii = 0; $ii < $n; $ii++) {
      list($stdout, $stderr) = execx(
        "(cd %s && ./launch_daemon.php %s --daemonize --phd=%s)",
        $launch_daemon,
        $daemon,
        $pid_dir);
      echo ".";
    }
    echo "\n";
    echo "Done.\n";

    break;

  case 'parse-commit':
    $commit = isset($argv[2]) ? $argv[2] : null;
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

    switch ($repo->getVersionControlSystem()) {
      case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
        $worker = new PhabricatorRepositoryGitCommitChangeParserWorker(
          $commit->getID());
        break;
      case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
        $worker = new PhabricatorRepositorySvnCommitChangeParserWorker(
          $commit->getID());
        break;
      default:
        throw new Exception("Unknown repository type!");
    }

    ExecFuture::pushEchoMode(true);

    $worker->doWork();

    echo "Done.\n";

    break;
  case '--help':
  case 'help':
  default:
    echo <<<EOHELP
phd - phabricator daemon launcher

launch <daemon>
  Start a daemon.

list
  List available daemons.

stop
  Stop all daemons.

status
  List running daemons.

stop
  Stop all running daemons.

parse-commit <rXnnnn>
  Parse a single commit.

EOHELP;
    exit(1);
}
