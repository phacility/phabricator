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

final class PhabricatorDaemonControl {


  public function executeListCommand() {
    $symbols = $this->loadAvailableDaemonClasses();

    $symbols = igroup($symbols, 'library');

    echo "\n";
    foreach ($symbols as $library => $symbol_list) {
      echo phutil_console_format("Daemons in library __%s__:\n", $library);
      foreach ($symbol_list as $symbol) {
        echo "    ".$symbol['name']."\n";
      }
      echo "\n";
    }

    return 0;
  }

  public function executeStatusCommand() {
    $daemons = $this->loadRunningDaemons();

    if (!$daemons) {
      echo "There are no running Phabricator daemons.\n";
      return 0;
    }

    printf(
      "%-5s\t%-24s\t%s\n",
      "PID",
      "Started",
      "Daemon");
    foreach ($daemons as $daemon) {
      printf(
        "%5s\t%-24s\t%s\n",
        $daemon->getPID(),
        $daemon->getEpochStarted()
          ? date('M j Y, g:i:s A', $daemon->getEpochStarted())
          : null,
        $daemon->getName());
    }

    return 0;
  }

  public function executeStopCommand() {
    $daemons = $this->loadRunningDaemons();
    if (!$daemons) {
      echo "There are no running Phabricator daemons.\n";
      return 0;
    }

    $running = $daemons;

    foreach ($running as $key => $daemon) {
      $pid = $daemon->getPID();
      $name = $daemon->getName();

      echo "Stopping daemon '{$name}' ({$pid})...\n";
      if (!$daemon->isRunning()) {
        echo "Daemon is not running.\n";
        unset($running[$key]);
      } else {
        posix_kill($pid, SIGINT);
      }
    }

    $start = time();
    do {
      foreach ($running as $key => $daemon) {
        $pid = $daemon->getPID();
        if (!$daemon->isRunning()) {
          echo "Daemon {$pid} exited normally.\n";
          unset($running[$key]);
        }
      }
      if (empty($running)) {
        break;
      }
      usleep(100000);
    } while (time() < $start + 15);

    foreach ($running as $key => $daemon) {
      $pid = $daemon->getPID();
      echo "KILLing daemon {$pid}.\n";
      posix_kill($pid, SIGKILL);
    }

    foreach ($daemons as $daemon) {
      if ($daemon->getPIDFile()) {
        Filesystem::remove($daemon->getPIDFile());
      }
    }

  }

  public function executeHelpCommand() {
    echo phutil_console_format(<<<EOHELP
**NAME**
        **phd** - phabricator daemon launcher

**COMMAND REFERENCE**

        **launch** [__n__] __daemon__
            Start a daemon (or n copies of a daemon).

        **list**
            List available daemons.

        **stop**
            Stop all daemons.

        **status**
            List running daemons.

        **stop**
            Stop all running daemons.

        **help**
            Show this help.

        **parse-commit** __rXnnnn__
            Parse a single commit.

        **repository-launch-master**
            Launches daemons to update and parse all tracked repositories. You
            must also launch Taskmaster daemons, either on the same machine or
            elsewhere. You should launch a master only one machine. For other
            machines, launch a 'readonly'.

        **repository-launch-readonly**
            Launches daemons to 'git pull' tracked git repositories so they
            stay up to date.

EOHELP
    );
    return 1;
  }

  public function launchDaemon($daemon, array $argv) {
    $symbols = $this->loadAvailableDaemonClasses();
    $symbols = ipull($symbols, 'name', 'name');
    if (empty($symbols[$daemon])) {
      throw new Exception("Daemon '{$daemon}' is not known.");
    }

    $pid_dir = $this->getControlDirectory('pid');

    $libphutil_root = dirname(phutil_get_library_root('phutil'));
    $launch_daemon = $libphutil_root.'/scripts/daemon/';

    // TODO: This should be a much better user experience.
    Filesystem::assertExists($pid_dir);
    Filesystem::assertIsDirectory($pid_dir);
    Filesystem::assertWritable($pid_dir);

    foreach ($argv as $key => $arg) {
      $argv[$key] = escapeshellarg($arg);
    }

    $future = new ExecFuture(
      "./launch_daemon.php ".
        "%s ".
        "--load-phutil-library=%s ".
        "--conduit-uri=%s ".
        "--daemonize ".
        "--phd=%s ".
        implode(' ', $argv),
      $daemon,
      phutil_get_library_root('phabricator'),
      PhabricatorEnv::getURI('/api/'),
      $pid_dir);

    // Play games to keep 'ps' looking reasonable.
    $future->setCWD($launch_daemon);

    $future->resolvex();
  }

  protected function getControlDirectory($dir) {
    return PhabricatorEnv::getEnvConfig('phd.pid-directory').'/'.$dir;
  }

  protected function loadAvailableDaemonClasses() {
    $loader = new PhutilSymbolLoader();
    return $loader
      ->setAncestorClass('PhutilDaemon')
      ->selectSymbolsWithoutLoading();
  }

  protected function loadRunningDaemons() {
    $results = array();

    $pid_dir = $this->getControlDirectory('pid');
    $pid_files = Filesystem::listDirectory($pid_dir);
    if (!$pid_files) {
      return $results;
    }

    foreach ($pid_files as $pid_file) {
      $pid_data = Filesystem::readFile($pid_dir.'/'.$pid_file);
      $dict = json_decode($pid_data, true);
      if (!is_array($dict)) {
        // Just return a hanging reference, since control code needs to be
        // robust against unusual system states.
        $dict = array();
      }
      $ref = PhabricatorDaemonReference::newFromDictionary($dict);
      $ref->setPIDFile($pid_dir.'/'.$pid_file);
      $results[] = $ref;
    }

    return $results;
  }

  protected function killDaemon(PhabricatorDaemonReference $ref) {
  }



}
