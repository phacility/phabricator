<?php

abstract class PhabricatorDaemonManagementWorkflow
  extends PhabricatorManagementWorkflow {

  private $runDaemonsAsUser = null;

  final protected function loadAvailableDaemonClasses() {
    return id(new PhutilSymbolLoader())
      ->setAncestorClass('PhutilDaemon')
      ->setConcreteOnly(true)
      ->selectSymbolsWithoutLoading();
  }

  final protected function getLogDirectory() {
    $path = PhabricatorEnv::getEnvConfig('phd.log-directory');
    return $this->getControlDirectory($path);
  }

  private function getControlDirectory($path) {
    if (!Filesystem::pathExists($path)) {
      list($err) = exec_manual('mkdir -p %s', $path);
      if ($err) {
        throw new Exception(
          pht(
            "%s requires the directory '%s' to exist, but it does not exist ".
            "and could not be created. Create this directory or update ".
            "'%s' in your configuration to point to an existing ".
            "directory.",
            'phd',
            $path,
            'phd.log-directory'));
      }
    }
    return $path;
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
          "No daemons match '%s'! Use '%s' for a list of available daemons.",
          $substring,
          'phd list'));
    } else if (count($match) > 1) {
      throw new PhutilArgumentUsageException(
        pht(
          "Specify a daemon unambiguously. Multiple daemons match '%s': %s.",
          $substring,
          implode(', ', $match)));
    }

    return head($match);
  }

  final protected function launchDaemons(
    array $daemons,
    $debug,
    $run_as_current_user = false) {

    // Convert any shorthand classnames like "taskmaster" into proper class
    // names.
    foreach ($daemons as $key => $daemon) {
      $class = $this->findDaemonClass($daemon['class']);
      $daemons[$key]['class'] = $class;
    }

    $console = PhutilConsole::getConsole();

    if (!$run_as_current_user) {
      // Check if the script is started as the correct user
      $phd_user = PhabricatorEnv::getEnvConfig('phd.user');
      $current_user = posix_getpwuid(posix_geteuid());
      $current_user = $current_user['name'];
      if ($phd_user && $phd_user != $current_user) {
        if ($debug) {
          throw new PhutilArgumentUsageException(
            pht(
              "You are trying to run a daemon as a nonstandard user, ".
              "and `%s` was not able to `%s` to the correct user. \n".
              'The daemons are configured to run as "%s", '.
              'but the current user is "%s". '."\n".
              'Use `%s` to run as a different user, pass `%s` to ignore this '.
              'warning, or edit `%s` to change the configuration.',
              'phd',
              'sudo',
              $phd_user,
              $current_user,
              'sudo',
              '--as-current-user',
              'phd.user'));
        } else {
          $this->runDaemonsAsUser = $phd_user;
          $console->writeOut(pht('Starting daemons as %s', $phd_user)."\n");
        }
      }
    }

    $this->printLaunchingDaemons($daemons, $debug);

    $trace = PhutilArgumentParser::isTraceModeEnabled();

    $flags = array();
    if ($trace) {
      $flags[] = '--trace';
    }

    if ($debug) {
      $flags[] = '--verbose';
    }

    $instance = $this->getInstance();
    if ($instance) {
      $flags[] = '-l';
      $flags[] = $instance;
    }

    $config = array();

    if (!$debug) {
      $config['daemonize'] = true;
    }

    if (!$debug) {
      $config['log'] = $this->getLogDirectory().'/daemons.log';
    }

    $config['daemons'] = $daemons;

    $command = csprintf('./phd-daemon %Ls', $flags);

    $phabricator_root = dirname(phutil_get_library_root('phabricator'));
    $daemon_script_dir = $phabricator_root.'/scripts/daemon/';

    if ($debug) {
      // Don't terminate when the user sends ^C; it will be sent to the
      // subprocess which will terminate normally.
      pcntl_signal(
        SIGINT,
        array(__CLASS__, 'ignoreSignal'));

      echo "\n    scripts/daemon/ \$ {$command}\n\n";

      $tempfile = new TempFile('daemon.config');
      Filesystem::writeFile($tempfile, json_encode($config));

      phutil_passthru(
        '(cd %s && exec %C < %s)',
        $daemon_script_dir,
        $command,
        $tempfile);
    } else {
      try {
        $this->executeDaemonLaunchCommand(
          $command,
          $daemon_script_dir,
          $config,
          $this->runDaemonsAsUser);
      } catch (Exception $ex) {
        throw new PhutilArgumentUsageException(
          pht(
            'Daemons are configured to run as user "%s" in configuration '.
            'option `%s`, but the current user is "%s" and `phd` was unable '.
            'to switch to the correct user with `sudo`. Command output:'.
            "\n\n".
            '%s',
            $phd_user,
            'phd.user',
            $current_user,
            $ex->getMessage()));
      }
    }
  }

  private function executeDaemonLaunchCommand(
    $command,
    $daemon_script_dir,
    array $config,
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
    $future->write(json_encode($config));
    list($stdout, $stderr) = $future->resolvex();

    if ($is_sudo) {
      // On OSX, `sudo -n` exits 0 when the user does not have permission to
      // switch accounts without a password. This is not consistent with
      // sudo on Linux, and seems buggy/broken. Check for this by string
      // matching the output.
      if (preg_match('/sudo: a password is required/', $stderr)) {
        throw new Exception(
          pht(
            '%s exited with a zero exit code, but emitted output '.
            'consistent with failure under OSX.',
            'sudo'));
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
      echo pht(
        "ERROR: The PHP extension '%s' is not installed. You must ".
        "install it to run daemons on this machine.\n",
        $ext);
      exit(1);
    }

    $extension = new ReflectionExtension($ext);
    foreach ($extension->getFunctions() as $function) {
      $function = $function->name;
      if (!function_exists($function)) {
        echo pht(
          "ERROR: The PHP function %s is disabled. You must ".
          "enable it to run daemons on this machine.\n",
          $function.'()');
        exit(1);
      }
    }
  }


/* -(  Commands  )----------------------------------------------------------- */


  final protected function executeStartCommand(array $options) {
    PhutilTypeSpec::checkMap(
      $options,
      array(
        'keep-leases' => 'optional bool',
        'force' => 'optional bool',
        'reserve' => 'optional float',
      ));

    $console = PhutilConsole::getConsole();

    if (!idx($options, 'force')) {
      $process_refs = $this->getOverseerProcessRefs();
      if ($process_refs) {
        $this->logWarn(
          pht('RUNNING DAEMONS'),
          pht('Daemons are already running:'));

        fprintf(STDERR, '%s', "\n");
        foreach ($process_refs as $process_ref) {
          fprintf(
            STDERR,
            '%s',
            tsprintf(
              "        %s %s\n",
              $process_ref->getPID(),
              $process_ref->getCommand()));
        }
        fprintf(STDERR, '%s', "\n");

        $this->logFail(
          pht('RUNNING DAEMONS'),
          pht(
            'Use "phd stop" to stop daemons, "phd restart" to restart '.
            'daemons, or "phd start --force" to ignore running processes.'));

        exit(1);
      }
    }

    if (idx($options, 'keep-leases')) {
      $console->writeErr("%s\n", pht('Not touching active task queue leases.'));
    } else {
      $console->writeErr("%s\n", pht('Freeing active task leases...'));
      $count = $this->freeActiveLeases();
      $console->writeErr(
        "%s\n",
        pht('Freed %s task lease(s).', new PhutilNumber($count)));
    }

    $daemons = array(
      array(
        'class' => 'PhabricatorRepositoryPullLocalDaemon',
        'label' => 'pull',
      ),
      array(
        'class' => 'PhabricatorTriggerDaemon',
        'label' => 'trigger',
      ),
      array(
        'class' => 'PhabricatorFactDaemon',
        'label' => 'fact',
      ),
      array(
        'class' => 'PhabricatorTaskmasterDaemon',
        'label' => 'task',
        'pool' => PhabricatorEnv::getEnvConfig('phd.taskmasters'),
        'reserve' => idx($options, 'reserve', 0),
      ),
    );

    $this->launchDaemons($daemons, $is_debug = false);

    $console->writeErr("%s\n", pht('Done.'));
    return 0;
  }

  final protected function executeStopCommand(array $options) {
    $grace_period = idx($options, 'graceful', 15);
    $force = idx($options, 'force');

    $query = id(new PhutilProcessQuery())
      ->withIsOverseer(true);

    $instance = $this->getInstance();
    if ($instance !== null && !$force) {
      $query->withInstances(array($instance));
    }

    try {
      $process_refs = $query->execute();
    } catch (Exception $ex) {
      // See T13321. If this fails for some reason, just continue for now so
      // that daemon management still works. In the long run, we don't expect
      // this to fail, but I don't want to break this workflow while we iron
      // bugs out.

      // See T12827. Particularly, this is likely to fail on Solaris.

      phlog($ex);

      $process_refs = array();
    }

    if (!$process_refs) {
      if ($instance !== null && !$force) {
        $this->logInfo(
          pht('NO DAEMONS'),
          pht(
            'There are no running daemons for the current instance ("%s"). '.
            'Use "--force" to stop daemons for all instances.',
            $instance));
      } else {
        $this->logInfo(
          pht('NO DAEMONS'),
          pht('There are no running daemons.'));
      }

      return 0;
    }

    $process_refs = mpull($process_refs, null, 'getPID');

    $stop_pids = array_keys($process_refs);
    $live_pids = $this->sendStopSignals($stop_pids, $grace_period);

    $stop_pids = array_fuse($stop_pids);
    $live_pids = array_fuse($live_pids);

    $dead_pids = array_diff_key($stop_pids, $live_pids);

    foreach ($dead_pids as $dead_pid) {
      $dead_ref = $process_refs[$dead_pid];
      $this->logOkay(
        pht('STOP'),
        pht(
          'Stopped PID %d ("%s")',
          $dead_pid,
          $dead_ref->getCommand()));
    }

    foreach ($live_pids as $live_pid) {
      $live_ref = $process_refs[$live_pid];
      $this->logFail(
        pht('SURVIVED'),
        pht(
          'Unable to stop PID %d ("%s").',
          $live_pid,
          $live_ref->getCommand()));
    }

    if ($live_pids) {
      $this->logWarn(
        pht('SURVIVORS'),
        pht(
          'Unable to stop all daemon processes. You may need to run this '.
          'command as root with "sudo".'));
    }

    return 0;
  }

  final protected function executeReloadCommand(array $pids) {
    $process_refs = $this->getOverseerProcessRefs();

    if (!$process_refs) {
      $this->logInfo(
        pht('NO DAEMONS'),
        pht('There are no running daemon processes to reload.'));

      return 0;
    }

    foreach ($process_refs as $process_ref) {
      $pid = $process_ref->getPID();

      $this->logInfo(
        pht('RELOAD'),
        pht('Reloading process %d...', $pid));

      posix_kill($pid, SIGHUP);
    }

    return 0;
  }

  private function sendStopSignals($pids, $grace_period) {
    // If we're doing a graceful shutdown, try SIGINT first.
    if ($grace_period) {
      $pids = $this->sendSignal($pids, SIGINT, $grace_period);
    }

    // If we still have daemons, SIGTERM them.
    if ($pids) {
      $pids = $this->sendSignal($pids, SIGTERM, 15);
    }

    // If the overseer is still alive, SIGKILL it.
    if ($pids) {
      $pids = $this->sendSignal($pids, SIGKILL, 0);
    }

    return $pids;
  }

  private function sendSignal(array $pids, $signo, $wait) {
    $console = PhutilConsole::getConsole();

    $pids = array_fuse($pids);

    foreach ($pids as $key => $pid) {
      if (!$pid) {
        // NOTE: We must have a PID to signal a daemon, since sending a signal
        // to PID 0 kills this process.
        unset($pids[$key]);
        continue;
      }

      switch ($signo) {
        case SIGINT:
          $message = pht('Interrupting process %d...', $pid);
          break;
        case SIGTERM:
          $message = pht('Terminating process %d...', $pid);
          break;
        case SIGKILL:
          $message = pht('Killing process %d...', $pid);
          break;
      }

      $console->writeOut("%s\n", $message);
      posix_kill($pid, $signo);
    }

    if ($wait) {
      $start = PhabricatorTime::getNow();
      do {
        foreach ($pids as $key => $pid) {
          if (!PhabricatorDaemonReference::isProcessRunning($pid)) {
            $console->writeOut(pht('Process %d exited.', $pid)."\n");
            unset($pids[$key]);
          }
        }
        if (empty($pids)) {
          break;
        }
        usleep(100000);
      } while (PhabricatorTime::getNow() < $start + $wait);
    }

    return $pids;
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


  private function printLaunchingDaemons(array $daemons, $debug) {
    $console = PhutilConsole::getConsole();

    if ($debug) {
      $console->writeOut(pht('Launching daemons (in debug mode):'));
    } else {
      $console->writeOut(pht('Launching daemons:'));
    }

    $log_dir = $this->getLogDirectory().'/daemons.log';
    $console->writeOut(
      "\n%s\n\n",
      pht('(Logs will appear in "%s".)', $log_dir));

    foreach ($daemons as $daemon) {
      $pool_size = pht('(Pool: %s)', idx($daemon, 'pool', 1));

      $console->writeOut(
        "    %s %s\n",
        $pool_size,
        $daemon['class'],
        implode(' ', idx($daemon, 'argv', array())));
    }
    $console->writeOut("\n");
  }

  protected function getAutoscaleReserveArgument() {
    return array(
      'name' => 'autoscale-reserve',
      'param' => 'ratio',
      'help' => pht(
        'Specify a proportion of machine memory which must be free '.
        'before autoscale pools will grow. For example, a value of 0.25 '.
        'means that pools will not grow unless the machine has at least '.
        '25%%%% of its RAM free.'),
    );
  }

  private function selectDaemonPIDs(array $daemons, array $pids) {
    $console = PhutilConsole::getConsole();

    $running_pids = array_fuse(mpull($daemons, 'getPID'));
    if (!$pids) {
      $select_pids = $running_pids;
    } else {
      // We were given a PID or set of PIDs to kill.
      $select_pids = array();
      foreach ($pids as $key => $pid) {
        if (!preg_match('/^\d+$/', $pid)) {
          $console->writeErr(pht("PID '%s' is not a valid PID.", $pid)."\n");
          continue;
        } else if (empty($running_pids[$pid])) {
          $console->writeErr(
            "%s\n",
            pht(
              'PID "%d" is not a known daemon PID.',
              $pid));
          continue;
        } else {
          $select_pids[$pid] = $pid;
        }
      }
    }

    return $select_pids;
  }

  protected function getOverseerProcessRefs() {
    $query = id(new PhutilProcessQuery())
      ->withIsOverseer(true);

    $instance = PhabricatorEnv::getEnvConfig('cluster.instance');
    if ($instance !== null) {
      $query->withInstances(array($instance));
    }

    return $query->execute();
  }

  protected function getInstance() {
    return PhabricatorEnv::getEnvConfig('cluster.instance');
  }


}
