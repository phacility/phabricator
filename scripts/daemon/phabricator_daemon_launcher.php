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

$control = new PhabricatorDaemonControl();

must_have_extension('pcntl');
must_have_extension('posix');

function must_have_extension($ext) {
  if (!extension_loaded($ext)) {
    echo "ERROR: The PHP extension '{$ext}' is not installed. You must ".
         "install it to run daemons on this machine.\n";
    exit(1);
  }
}

$command = isset($argv[1]) ? $argv[1] : 'help';
switch ($command) {
  case 'list':
    $err = $control->executeListCommand();
    exit($err);

  case 'status':
    $err = $control->executeStatusCommand();
    exit($err);

  case 'stop':
    $pass_argv = array_slice($argv, 2);
    $err = $control->executeStopCommand($pass_argv);
    exit($err);

  case 'restart':
    $err = $control->executeStopCommand(array());
    if ($err) {
      exit($err);
    }
    /* Fall Through */
  case 'start':
    $running = $control->loadRunningDaemons();
    // "running" might not mean actually running so much as was running at
    // some point. ergo, do a quick grouping and only barf if daemons are
    // *actually* running.
    $running_dict = mgroup($running, 'isRunning');
    if (!empty($running_dict[true])) {
      echo phutil_console_wrap(
        "phd start: Unable to start daemons because daemons are already ".
        "running.\n".
        "You can view running daemons with 'phd status'.\n".
        "You can stop running daemons with 'phd stop'.\n".
        "You can use 'phd restart' to stop all daemons before starting new ".
        "daemons.\n");
      exit(1);
    }

    $daemons = array(
      array('PhabricatorRepositoryPullLocalDaemon', array()),
      array('PhabricatorGarbageCollectorDaemon', array()),
    );

    $taskmasters = PhabricatorEnv::getEnvConfig('phd.start-taskmasters');
    for ($ii = 0; $ii < $taskmasters; $ii++) {
      $daemons[] = array('PhabricatorTaskmasterDaemon', array());
    }

    will_launch($control);
    foreach ($daemons as $spec) {
      list($name, $argv) = $spec;
      echo "Launching '{$name}'...\n";
      $control->launchDaemon($name, $argv);
    }

    echo "Done.\n";
    break;

  case 'repository-launch-readonly':
  case 'repository-launch-master':
    if ($command == 'repository-launch-readonly') {
      $daemon_args = array(
        '--',
        '--no-discovery',
      );
    } else {
      $daemon_args = array();
    }

    $need_launch = phd_load_tracked_repositories();
    if (!$need_launch) {
      echo "There are no repositories with tracking enabled.\n";
      exit(1);
    }

    will_launch($control);

    echo "Launching PullLocal daemon...\n";
    $control->launchDaemon(
      'PhabricatorRepositoryPullLocalDaemon',
      $daemon_args);

    echo "NOTE: '{$command}' is deprecated. Consult the documentation.\n";

    echo "Done.\n";
    break;

  case 'launch':
  case 'debug':
    $is_debug = ($argv[1] == 'debug');

    $daemon = idx($argv, 2);
    if (!$daemon) {
      throw new Exception("Daemon name required!");
    }

    $pass_argv = array_slice($argv, 3);

    $n = 1;
    if (!$is_debug) {
      if (is_numeric($daemon)) {
        $n = $daemon;
        if ($n < 1) {
          throw new Exception("Count must be at least 1!");
        }
        $daemon = idx($argv, 3);
        if (!$daemon) {
          throw new Exception("Daemon name required!");
        }
        $pass_argv = array_slice($argv, 4);
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

    $with_logs = true;
    if ($is_debug) {
      // In debug mode, we emit errors straight to stdout, so nothing useful
      // will show up in the logs. Don't echo the message about stuff showing
      // up in them, since it would be confusing.
      $with_logs = false;
    }

    will_launch($control, $with_logs);

    if ($is_debug) {
      echo "Launching {$daemon} in debug mode (nondaemonized)...\n";
    } else {
      echo "Launching {$n} x {$daemon}";
    }

    for ($ii = 0; $ii < $n; $ii++) {
      $control->launchDaemon($daemon, $pass_argv, $is_debug);
      if (!$is_debug) {
        echo ".";
      }
    }

    echo "\n";
    echo "Done.\n";

    break;

  case '--help':
  case 'help':
  default:
    $err = $control->executeHelpCommand();
    exit($err);
}

function phd_load_tracked_repositories() {
  $repositories = id(new PhabricatorRepository())->loadAll();
  foreach ($repositories as $key => $repository) {
    if (!$repository->isTracked()) {
      unset($repositories[$key]);
    }
  }

  return $repositories;
}

function will_launch($control, $with_logs = true) {
  echo "Staging launch...\n";
  $control->pingConduit();
  if ($with_logs) {
    $log_dir = $control->getLogDirectory().'/daemons.log';
    echo "NOTE: Logs will appear in '{$log_dir}'.\n\n";
  }
}

