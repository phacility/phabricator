<?php

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
      return 1;
    }

    $status = 0;
    printf(
      "%-5s\t%-24s\t%s\n",
      "PID",
      "Started",
      "Daemon");
    foreach ($daemons as $daemon) {
      $name = $daemon->getName();
      if (!$daemon->isRunning()) {
        $daemon->updateStatus(PhabricatorDaemonLog::STATUS_DEAD);
        $status = 2;
        $name = '<DEAD> '.$name;
      }
      printf(
        "%5s\t%-24s\t%s\n",
        $daemon->getPID(),
        $daemon->getEpochStarted()
          ? date('M j Y, g:i:s A', $daemon->getEpochStarted())
          : null,
        $name);
    }

    return $status;
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
        $daemon->updateStatus(PhabricatorDaemonLog::STATUS_EXITED);
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

        **start**
            Start the normal collection of daemons that Phabricator uses. This
            is appropriate for most installs. If you want to customize what
            is launched, you can use **launch** for fine-grained control.

        **restart**
            Stop all running daemons, then start a standard loadout.

        **stop** [PID ...]
            Stop all running daemons if no PIDs are given, or a particular
            PID or set of PIDs, if they are supplied.

        **launch** [__n__] __daemon__ [argv ...]
        **debug** __daemon__ [argv ...]
            Start a daemon (or n copies of a daemon).
            With **debug**, do not daemonize. Use this if you're having trouble
            getting daemons working.

        **list**
            List available daemons.

        **status**
            List running daemons. This command will exit with a non-zero exit
            status if any daemons are not running.

        **help**
            Show this help.

        **repository-launch-master**
            DEPRECATED. Use 'phd start'.

        **repository-launch-readonly**
            DEPRECATED. Use 'phd launch pulllocal -- --no-discovery'.

EOHELP
    );
    return 1;
  }

  public function pingConduit() {
    // It's fairly common to have issues here, e.g. because Phabricator isn't
    // running, isn't accessible, you put the domain in your hostsfile but it
    // isn't available on the production host, etc. If any of this doesn't work,
    // conduit will throw.

    $conduit = new ConduitClient(PhabricatorEnv::getURI('/api/'));
    $conduit->callMethodSynchronous('conduit.ping', array());
  }

  public function launchDaemon($daemon, array $argv, $debug = false) {
    $symbols = $this->loadAvailableDaemonClasses();
    $symbols = ipull($symbols, 'name', 'name');
    if (empty($symbols[$daemon])) {
      throw new Exception(
        "Daemon '{$daemon}' is not loaded, misspelled or abstract.");
    }

    $libphutil_root = dirname(phutil_get_library_root('phutil'));
    $launch_daemon = $libphutil_root.'/scripts/daemon/';

    foreach ($argv as $key => $arg) {
      $argv[$key] = escapeshellarg($arg);
    }

    $flags = array();
    if ($debug || PhabricatorEnv::getEnvConfig('phd.trace')) {
      $flags[] = '--trace';
    }

    if ($debug || PhabricatorEnv::getEnvConfig('phd.verbose')) {
      $flags[] = '--verbose';
    }

    if (!$debug) {
      $flags[] = '--daemonize';
    }

    $bootloader = PhutilBootloader::getInstance();
    foreach ($bootloader->getAllLibraries() as $library) {
      if ($library == 'phutil') {
        // No need to load libphutil, it's necessarily loaded implicitly by the
        // daemon itself.
        continue;
      }
      $flags[] = csprintf(
        '--load-phutil-library=%s',
        phutil_get_library_root($library));
    }

    $flags[] = csprintf('--conduit-uri=%s', PhabricatorEnv::getURI('/api/'));

    if (!$debug) {
      $log_file = $this->getLogDirectory().'/daemons.log';
      $flags[] = csprintf('--log=%s', $log_file);
    }

    $pid_dir = $this->getPIDDirectory();

    // TODO: This should be a much better user experience.
    Filesystem::assertExists($pid_dir);
    Filesystem::assertIsDirectory($pid_dir);
    Filesystem::assertWritable($pid_dir);

    $flags[] = csprintf('--phd=%s', $pid_dir);

    $command = csprintf(
      './launch_daemon.php %s %C %C',
      $daemon,
      implode(' ', $flags),
      implode(' ', $argv));

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

  private function getControlDirectory($path) {
    if (!Filesystem::pathExists($path)) {
      list($err) = exec_manual('mkdir -p %s', $path);
      if ($err) {
        throw new Exception(
          "phd requires the directory '{$path}' to exist, but it does not ".
          "exist and could not be created. Create this directory or update ".
          "'phd.pid-directory' / 'phd.log-directory' in your configuration ".
          "to point to an existing directory.");
      }
    }
    return $path;
  }

  public function getPIDDirectory() {
    $path = PhabricatorEnv::getEnvConfig('phd.pid-directory');
    return $this->getControlDirectory($path);
  }

  public function getLogDirectory() {
    $path = PhabricatorEnv::getEnvConfig('phd.log-directory');
    return $this->getControlDirectory($path);
  }

  protected function loadAvailableDaemonClasses() {
    $loader = new PhutilSymbolLoader();
    return $loader
      ->setAncestorClass('PhutilDaemon')
      ->setConcreteOnly(true)
      ->selectSymbolsWithoutLoading();
  }

  public function loadRunningDaemons() {
    $results = array();

    $pid_dir = $this->getPIDDirectory();
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
