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
      $name = $daemon->getName();
      if (!$daemon->isRunning()) {
        $name = '<DEAD> '.$name;
        if ($daemon->getPIDFile()) {
          Filesystem::remove($daemon->getPIDFile());
        }
      }
      printf(
        "%5s\t%-24s\t%s\n",
        $daemon->getPID(),
        $daemon->getEpochStarted()
          ? date('M j Y, g:i:s A', $daemon->getEpochStarted())
          : null,
        $name);
    }

    return 0;
  }

  public function executeStopCommand($pids = null) {
    $daemons = $this->loadRunningDaemons();
    if (!$daemons) {
      echo "There are no running Phabricator daemons.\n";
      return 0;
    }

    $daemons = mpull($daemons, null, 'getPID');

    $running = array();
    if ($pids == null) {
      $running = $daemons;
    } else {
      // We were given a PID or set of PIDs to kill.
      foreach ($pids as $key => $pid) {
        if (!preg_match('/^\d+$/', $pid)) {
          echo "'{$pid}' is not a valid PID.\n";
          continue;
        } else if (empty($daemons[$pid])) {
          echo "'{$pid}' is not Phabricator-controlled PID. Not killing.\n";
          continue;
        } else {
          $running[] = $daemons[$pid];
        }
      }
    }

    if (empty($running)) {
      echo "No daemons to kill.\n";
      return 0;
    }

    $all_daemons = $running;

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

    foreach ($all_daemons as $daemon) {
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

        **launch** [__n__] __daemon__ [argv ...]
        **debug** __daemon__ [argv ...]
            Start a daemon (or n copies of a daemon).
            With **debug**, do not daemonize. Use this if you're having trouble
            getting daemons working.

        **list**
            List available daemons.

        **status**
            List running daemons.

        **stop** [PID ...]
            Stop all running daemons if no PIDs are given, or a particular
            PID or set of PIDs, if they are supplied.

        **help**
            Show this help.

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

  public function pingConduit() {
    // It's fairly common to have issues here, e.g. because Phabricator isn't
    // running, isn't accessible, you put the domain in your hostsfile but it
    // isn't available on the production host, etc. If any of this doesn't work,
    // conduit will throw.

    // We do this here rather than in the daemon since there's an HTTPS + curl
    // + fork issue of some kind that makes
    $conduit = new ConduitClient(PhabricatorEnv::getURI('/api/'));
    $conduit->setTimeout(5);
    $conduit->callMethodSynchronous('conduit.ping', array());
  }

  public function launchDaemon($daemon, array $argv, $debug = false) {
    $symbols = $this->loadAvailableDaemonClasses();
    $symbols = ipull($symbols, 'name', 'name');
    if (empty($symbols[$daemon])) {
      throw new Exception(
        "Daemon '{$daemon}' is not loaded, misspelled or abstract.");
    }

    $pid_dir = $this->getControlDirectory('pid');
    $log_dir = $this->getControlDirectory('log').'/daemons.log';

    $libphutil_root = dirname(phutil_get_library_root('phutil'));
    $launch_daemon = $libphutil_root.'/scripts/daemon/';

    // TODO: This should be a much better user experience.
    Filesystem::assertExists($pid_dir);
    Filesystem::assertIsDirectory($pid_dir);
    Filesystem::assertWritable($pid_dir);

    foreach ($argv as $key => $arg) {
      $argv[$key] = escapeshellarg($arg);
    }

    $bootloader = PhutilBootloader::getInstance();
    $all_libraries = $bootloader->getAllLibraries();

    $non_default_libraries = array_diff(
      $all_libraries,
      array('phutil', 'phabricator'));

    $extra_libraries = array();
    foreach ($non_default_libraries as $library) {
      $extra_libraries[] = csprintf(
        '--load-phutil-library=%s',
        phutil_get_library_root($library));
    }

    $command = csprintf(
      "./launch_daemon.php ".
        "%s ".
        "--load-phutil-library=%s ".
        "%C ".
        "--conduit-uri=%s ".
        "--phd=%s ".
        ($debug ? '--trace ' : '--daemonize '),
      $daemon,
      phutil_get_library_root('phabricator'),
      implode(' ', $extra_libraries),
      PhabricatorEnv::getURI('/api/'),
      $pid_dir);

    if (!$debug) {
      // If we're running "phd debug", send output straight to the console
      // instead of to a logfile.
      $command = csprintf("%C --log=%s", $command, $log_dir);
    }

    // Append the daemon's argv.
    $command = csprintf("%C %C", $command, implode(' ', $argv));

    if ($debug) {
      // Don't terminate when the user sends ^C; it will be sent to the
      // subprocess which will terminate normally.
      pcntl_signal(
        SIGINT,
        array('PhabricatorDaemonControl', 'ignoreSignal'));

      echo "\n    libphutil/scripts/daemon/ \$ {$command}\n\n";

      phutil_passthru('(cd %s && exec %C)', $launch_daemon, $command);
    } else {
      $future = new ExecFuture('exec %C', $command);
      // Play games to keep 'ps' looking reasonable.
      $future->setCWD($launch_daemon);
      $future->resolvex();
    }
  }

  public static function ignoreSignal($signo) {
    return;
  }

  public function getControlDirectory($dir) {
    $path = PhabricatorEnv::getEnvConfig('phd.pid-directory').'/'.$dir;
    if (!Filesystem::pathExists($path)) {
      list($err) = exec_manual('mkdir -p %s', $path);
      if ($err) {
        throw new Exception(
          "phd requires the directory '{$path}' to exist, but it does not ".
          "exist and could not be created. Create this directory or update ".
          "'phd.pid-directory' in your configuration to point to an existing ".
          "directory.");
      }
    }
    return $path;
  }

  protected function loadAvailableDaemonClasses() {
    $loader = new PhutilSymbolLoader();
    return $loader
      ->setAncestorClass('PhutilDaemon')
      ->setConcreteOnly(true)
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
