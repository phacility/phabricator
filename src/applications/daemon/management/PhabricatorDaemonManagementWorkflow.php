<?php

abstract class PhabricatorDaemonManagementWorkflow
  extends PhabricatorManagementWorkflow {

  private $runDaemonsAsUser = null;

  protected final function loadAvailableDaemonClasses() {
    $loader = new PhutilSymbolLoader();
    return $loader
      ->setAncestorClass('PhutilDaemon')
      ->setConcreteOnly(true)
      ->selectSymbolsWithoutLoading();
  }

  protected final function getPIDDirectory() {
    $path = PhabricatorEnv::getEnvConfig('phd.pid-directory');
    return $this->getControlDirectory($path);
  }

  protected final function getLogDirectory() {
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

  protected final function loadRunningDaemons() {
    $daemons = array();

    $pid_dir = $this->getPIDDirectory();
    $pid_files = Filesystem::listDirectory($pid_dir);

    foreach ($pid_files as $pid_file) {
      $daemons[] = PhabricatorDaemonReference::newFromFile(
        $pid_dir.'/'.$pid_file);
    }

    return $daemons;
  }

  protected final function loadAllRunningDaemons() {
    $local_daemons = $this->loadRunningDaemons();

    $local_ids = array();
    foreach ($local_daemons as $daemon) {
      $daemon_log = $daemon->getDaemonLog();

      if ($daemon_log) {
        $local_ids[] = $daemon_log->getID();
      }
    }

    $remote_daemons = id(new PhabricatorDaemonLogQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withoutIDs($local_ids)
      ->withStatus(PhabricatorDaemonLogQuery::STATUS_ALIVE)
      ->execute();

    return array_merge($local_daemons, $remote_daemons);
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
          "No daemons match '%s'! Use 'phd list' for a list of available ".
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

  protected final function launchDaemon(
    $class,
    array $argv,
    $debug,
    $run_as_current_user = false) {

    $daemon = $this->findDaemonClass($class);
    $console = PhutilConsole::getConsole();

    if (!$run_as_current_user) {
      // Check if the script is started as the correct user
      $phd_user = PhabricatorEnv::getEnvConfig('phd.user');
      $current_user = posix_getpwuid(posix_geteuid());
      $current_user = $current_user['name'];
      if ($phd_user && $phd_user != $current_user) {
        if ($debug) {
          throw new PhutilArgumentUsageException(pht(
            'You are trying to run a daemon as a nonstandard user, '.
            'and `phd` was not able to `sudo` to the correct user. '."\n".
            'Phabricator is configured to run daemons as "%s", '.
            'but the current user is "%s". '."\n".
            'Use `sudo` to run as a different user, pass `--as-current-user` '.
            'to ignore this warning, or edit `phd.user` '.
            'to change the configuration.', $phd_user, $current_user));
        } else {
          $this->runDaemonsAsUser = $phd_user;
          $console->writeOut(pht('Starting daemons as %s', $phd_user)."\n");
        }
      }
    }

    if ($debug) {
      if ($argv) {
        $console->writeOut(
          pht(
            "Launching daemon \"%s\" in debug mode (not daemonized) ".
            "with arguments %s.\n",
            $daemon,
            csprintf('%LR', $argv)));
      } else {
        $console->writeOut(
          pht(
            "Launching daemon \"%s\" in debug mode (not daemonized).\n",
            $daemon));
      }
    } else {
      if ($argv) {
        $console->writeOut(
          pht(
            "Launching daemon \"%s\" with arguments %s.\n",
            $daemon,
            csprintf('%LR', $argv)));
      } else {
        $console->writeOut(
          pht(
            "Launching daemon \"%s\".\n",
            $daemon));
      }
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
      try {
        $this->executeDaemonLaunchCommand(
          $command,
          $daemon_script_dir,
          $this->runDaemonsAsUser);
      } catch (Exception $e) {
        // Retry without sudo
        $console->writeOut(pht(
          "sudo command failed. Starting daemon as current user\n"));
        $this->executeDaemonLaunchCommand(
          $command,
          $daemon_script_dir);
      }
    }
  }

  private function executeDaemonLaunchCommand(
    $command,
    $daemon_script_dir,
    $run_as_user = null) {

    $is_sudo = false;
    if ($run_as_user) {
      // If anything else besides sudo should be
      // supported then insert it here (runuser, su, ...)
      $command = csprintf(
        'sudo -En -u %s -- %C',
        $run_as_user,
        $command);
      $is_sudo = true;
    }
    $future = new ExecFuture('exec %C', $command);
    // Play games to keep 'ps' looking reasonable.
    $future->setCWD($daemon_script_dir);
    list($stdout, $stderr) = $future->resolvex();

    if ($is_sudo) {
      // On OSX, `sudo -n` exits 0 when the user does not have permission to
      // switch accounts without a password. This is not consistent with
      // sudo on Linux, and seems buggy/broken. Check for this by string
      // matching the output.
      if (preg_match('/sudo: a password is required/', $stderr)) {
        throw new Exception(
          pht(
            'sudo exited with a zero exit code, but emitted output '.
            'consistent with failure under OSX.'));
      }
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

  protected final function willLaunchDaemons() {
    $console = PhutilConsole::getConsole();
    $console->writeErr(pht('Preparing to launch daemons.')."\n");

    $log_dir = $this->getLogDirectory().'/daemons.log';
    $console->writeErr(pht("NOTE: Logs will appear in '%s'.", $log_dir)."\n\n");
  }


/* -(  Commands  )----------------------------------------------------------- */


  protected final function executeStartCommand($keep_leases = false) {
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

    if ($keep_leases) {
      $console->writeErr("%s\n", pht('Not touching active task queue leases.'));
    } else {
      $console->writeErr("%s\n", pht('Freeing active task leases...'));
      $count = $this->freeActiveLeases();
      $console->writeErr(
        "%s\n",
        pht('Freed %s task lease(s).', new PhutilNumber($count)));
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

    $console->writeErr(pht('Done.')."\n");
    return 0;
  }

  protected final function executeStopCommand(
    array $pids,
    $grace_period,
    $force) {

    $console = PhutilConsole::getConsole();

    $daemons = $this->loadRunningDaemons();
    if (!$daemons) {
      $survivors = array();
      if (!$pids) {
        $survivors = $this->processRogueDaemons(
          $grace_period,
          $warn = true,
          $force);
      }
      if (!$survivors) {
        $console->writeErr(pht(
          'There are no running Phabricator daemons.')."\n");
      }
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
      $console->writeErr(pht('No daemons to kill.')."\n");
      return 0;
    }

    $all_daemons = $running;
    // don't specify force here as that's about rogue daemons
    $this->sendStopSignals($running, $grace_period);

    foreach ($all_daemons as $daemon) {
      if ($daemon->getPIDFile()) {
        Filesystem::remove($daemon->getPIDFile());
      }
    }

    $this->processRogueDaemons($grace_period, !$pids, $force);

    return 0;
  }

  private function processRogueDaemons($grace_period, $warn, $force_stop) {
    $console = PhutilConsole::getConsole();

    $rogue_daemons = PhutilDaemonOverseer::findRunningDaemons();
    if ($rogue_daemons) {
      if ($force_stop) {
        $stop_rogue_daemons = $this->buildRogueDaemons($rogue_daemons);
        $survivors = $this->sendStopSignals(
          $stop_rogue_daemons,
          $grace_period,
          $force_stop);
        if ($survivors) {
          $console->writeErr(pht(
            'Unable to stop processes running without pid files. Try running '.
            'this command again with sudo.'."\n"));
        }
      } else if ($warn) {
        $console->writeErr($this->getForceStopHint($rogue_daemons)."\n");
      }
    }
    return $rogue_daemons;
  }

  private function getForceStopHint($rogue_daemons) {
    $debug_output = '';
    foreach ($rogue_daemons as $rogue) {
      $debug_output .= $rogue['pid'].' '.$rogue['command']."\n";
    }
    return pht(
      'There are processes running that look like Phabricator daemons but '.
      'have no corresponding PID files:'."\n\n".'%s'."\n\n".
      'Stop these processes by re-running this command with the --force '.
      'parameter.',
      $debug_output);
  }

  private function buildRogueDaemons(array $daemons) {
    $rogue_daemons = array();
    foreach ($daemons as $pid => $data) {
      $rogue_daemons[] =
        PhabricatorDaemonReference::newFromRogueDictionary($data);
    }
    return $rogue_daemons;
  }

  private function sendStopSignals($daemons, $grace_period, $force = false) {
    // If we're doing a graceful shutdown, try SIGINT first.
    if ($grace_period) {
      $daemons = $this->sendSignal($daemons, SIGINT, $grace_period, $force);
    }

    // If we still have daemons, SIGTERM them.
    if ($daemons) {
      $daemons = $this->sendSignal($daemons, SIGTERM, 15, $force);
    }

    // If the overseer is still alive, SIGKILL it.
    if ($daemons) {
      $daemons = $this->sendSignal($daemons, SIGKILL, 0, $force);
    }
    return $daemons;
  }

  private function sendSignal(array $daemons, $signo, $wait, $force = false) {
    $console = PhutilConsole::getConsole();

    foreach ($daemons as $key => $daemon) {
      $pid = $daemon->getPID();
      $name = $daemon->getName();

      if (!$pid && !$force) {
        $console->writeOut("%s\n", pht("Daemon '%s' has no PID!", $name));
        unset($daemons[$key]);
        continue;
      }

      switch ($signo) {
        case SIGINT:
          $message = pht("Interrupting daemon '%s' (%s)...", $name, $pid);
          break;
        case SIGTERM:
          $message = pht("Terminating daemon '%s' (%s)...", $name, $pid);
          break;
        case SIGKILL:
          $message = pht("Killing daemon '%s' (%s)...", $name, $pid);
          break;
      }

      $console->writeOut("%s\n", $message);
      posix_kill($pid, $signo);
    }

    if ($wait) {
      $start = PhabricatorTime::getNow();
      do {
        foreach ($daemons as $key => $daemon) {
          $pid = $daemon->getPID();
          if (!$daemon->isRunning()) {
            $console->writeOut(pht('Daemon %s exited.', $pid)."\n");
            unset($daemons[$key]);
          }
        }
        if (empty($daemons)) {
          break;
        }
        usleep(100000);
      } while (PhabricatorTime::getNow() < $start + $wait);
    }

    return $daemons;
  }

  private function freeActiveLeases() {
    $task_table = id(new PhabricatorWorkerActiveTask());
    $conn_w = $task_table->establishConnection('w');
    queryfx(
      $conn_w,
      'UPDATE %T SET leaseExpires = UNIX_TIMESTAMP()
        WHERE leaseExpires > UNIX_TIMESTAMP()',
      $task_table->getTableName());
    return $conn_w->getAffectedRows();
  }

}
