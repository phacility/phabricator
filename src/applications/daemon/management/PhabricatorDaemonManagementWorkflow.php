<?php

abstract class PhabricatorDaemonManagementWorkflow
  extends PhutilArgumentWorkflow {

  public function isExecutable() {
    return true;
  }

  protected function loadAvailableDaemonClasses() {
    $loader = new PhutilSymbolLoader();
    return $loader
      ->setAncestorClass('PhutilDaemon')
      ->setConcreteOnly(true)
      ->selectSymbolsWithoutLoading();
  }

  public function getPIDDirectory() {
    $path = PhabricatorEnv::getEnvConfig('phd.pid-directory');
    return $this->getControlDirectory($path);
  }

  public function getLogDirectory() {
    $path = PhabricatorEnv::getEnvConfig('phd.log-directory');
    return $this->getControlDirectory($path);
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

  private function findDaemonClass($substring) {
    $symbols = $this->loadAvailableDaemonClasses();

    $symbols = ipull($symbols, 'name');
    $match = array();
    foreach ($symbols as $symbol) {
      if (stripos($symbol, $substring) !== false) {
        if (strtolower($symbol) == strtolower($substring)) {
          $match = array($symbol);
          break;
        } else {
          $match[] = $symbol;
        }
      }
    }

    if (count($match) == 0) {
      throw new PhutilArgumentUsageException(
        pht(
          "No daemons match '%s'! Use 'phd list' for a list of avialable ".
          "daemons.",
          $substring));
    } else if (count($match) > 1) {
      throw new PhutilArgumentUsageException(
        pht(
          "Specify a daemon unambiguously. Multiple daemons match '%s': %s.",
          $substring,
          implode(', ', $match)));
    }

    return head($match);
  }


  protected function launchDaemon($class, array $argv, $debug) {
    $daemon = $this->findDaemonClass($class);
    $console = PhutilConsole::getConsole();

    if ($debug) {
      $console->writeOut(
        pht(
          'Launching daemon "%s" in debug mode (not daemonized).',
          $daemon)."\n");
    } else {
      $console->writeOut(
        pht(
          'Launching daemon "%s".',
          $daemon)."\n");
    }

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
      './phd-daemon %s %C %C',
      $daemon,
      implode(' ', $flags),
      implode(' ', $argv));

    $phabricator_root = dirname(phutil_get_library_root('phabricator'));
    $daemon_script_dir = $phabricator_root.'/scripts/daemon/';

    if ($debug) {
      // Don't terminate when the user sends ^C; it will be sent to the
      // subprocess which will terminate normally.
      pcntl_signal(
        SIGINT,
        array(__CLASS__, 'ignoreSignal'));

      echo "\n    phabricator/scripts/daemon/ \$ {$command}\n\n";

      phutil_passthru('(cd %s && exec %C)', $daemon_script_dir, $command);
    } else {
      $future = new ExecFuture('exec %C', $command);
      // Play games to keep 'ps' looking reasonable.
      $future->setCWD($daemon_script_dir);
      $future->resolvex();
    }
  }

  public static function ignoreSignal($signo) {
    return;
  }

  public static function requireExtensions() {
    self::mustHaveExtension('pcntl');
    self::mustHaveExtension('posix');
  }

  private static function mustHaveExtension($ext) {
    if (!extension_loaded($ext)) {
      echo "ERROR: The PHP extension '{$ext}' is not installed. You must ".
           "install it to run daemons on this machine.\n";
      exit(1);
    }

    $extension = new ReflectionExtension($ext);
    foreach ($extension->getFunctions() as $function) {
      $function = $function->name;
      if (!function_exists($function)) {
        echo "ERROR: The PHP function {$function}() is disabled. You must ".
             "enable it to run daemons on this machine.\n";
        exit(1);
      }
    }
  }

  protected function willLaunchDaemons() {
    $console = PhutilConsole::getConsole();
    $console->writeErr(pht('Preparing to launch daemons.')."\n");

    $log_dir = $this->getLogDirectory().'/daemons.log';
    $console->writeErr(pht("NOTE: Logs will appear in '%s'.", $log_dir)."\n\n");
  }


/* -(  Commands  )----------------------------------------------------------- */


  protected function executeStartCommand() {
    $console = PhutilConsole::getConsole();

    $running = $this->loadRunningDaemons();

    // This may include daemons which were launched but which are no longer
    // running; check that we actually have active daemons before failing.
    foreach ($running as $daemon) {
      if ($daemon->isRunning()) {
        $message = pht(
          "phd start: Unable to start daemons because daemons are already ".
          "running.\n".
          "You can view running daemons with 'phd status'.\n".
          "You can stop running daemons with 'phd stop'.\n".
          "You can use 'phd restart' to stop all daemons before starting new ".
          "daemons.");

        $console->writeErr("%s\n", $message);
        exit(1);
      }
    }

    $daemons = array(
      array('PhabricatorRepositoryPullLocalDaemon', array()),
      array('PhabricatorGarbageCollectorDaemon', array()),
    );

    $taskmasters = PhabricatorEnv::getEnvConfig('phd.start-taskmasters');
    for ($ii = 0; $ii < $taskmasters; $ii++) {
      $daemons[] = array('PhabricatorTaskmasterDaemon', array());
    }

    $this->willLaunchDaemons();

    foreach ($daemons as $spec) {
      list($name, $argv) = $spec;
      $this->launchDaemon($name, $argv, $is_debug = false);
    }

    $console->writeErr(pht("Done.")."\n");

    return 0;
  }


  protected function executeStopCommand(array $pids) {
    $console = PhutilConsole::getConsole();

    $daemons = $this->loadRunningDaemons();
    if (!$daemons) {
      $console->writeErr(pht('There are no running Phabricator daemons.')."\n");
      return 0;
    }

    $daemons = mpull($daemons, null, 'getPID');

    $running = array();
    if (!$pids) {
      $running = $daemons;
    } else {
      // We were given a PID or set of PIDs to kill.
      foreach ($pids as $key => $pid) {
        if (!preg_match('/^\d+$/', $pid)) {
          $console->writeErr(pht("PID '%s' is not a valid PID.", $pid)."\n");
          continue;
        } else if (empty($daemons[$pid])) {
          $console->writeErr(
            pht(
              "PID '%s' is not a Phabricator daemon PID. It will not ".
              "be killed.",
              $pid)."\n");
          continue;
        } else {
          $running[] = $daemons[$pid];
        }
      }
    }

    if (empty($running)) {
      $console->writeErr(pht("No daemons to kill.")."\n");
      return 0;
    }

    $all_daemons = $running;
    foreach ($running as $key => $daemon) {
      $pid = $daemon->getPID();
      $name = $daemon->getName();

      $console->writeErr(pht("Stopping daemon '%s' (%s)...", $name, $pid)."\n");
      if (!$daemon->isRunning()) {
        $console->writeErr(pht("Daemon is not running.")."\n");
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
          $console->writeOut(pht('Daemon %s exited normally.', $pid)."\n");
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
      $console->writeErr(pht('Sending daemon %s a SIGKILL.', $pid)."\n");
      posix_kill($pid, SIGKILL);
    }

    foreach ($all_daemons as $daemon) {
      if ($daemon->getPIDFile()) {
        Filesystem::remove($daemon->getPIDFile());
      }
    }

    return 0;
  }

}
