<?php

final class PhutilDaemonHandle extends Phobject {

  const EVENT_DID_LAUNCH    = 'daemon.didLaunch';
  const EVENT_DID_LOG       = 'daemon.didLogMessage';
  const EVENT_DID_HEARTBEAT = 'daemon.didHeartbeat';
  const EVENT_WILL_GRACEFUL = 'daemon.willGraceful';
  const EVENT_WILL_EXIT     = 'daemon.willExit';

  private $pool;
  private $properties;
  private $future;
  private $argv;

  private $restartAt;
  private $busyEpoch;

  private $daemonID;
  private $deadline;
  private $heartbeat;
  private $stdoutBuffer;
  private $shouldRestart = true;
  private $shouldShutdown;
  private $hibernating = false;
  private $shouldSendExitEvent = false;

  private function __construct() {
    // <empty>
  }

  public static function newFromConfig(array $config) {
    PhutilTypeSpec::checkMap(
      $config,
      array(
        'class' => 'string',
        'argv' => 'optional list<string>',
        'load' => 'optional list<string>',
        'log' => 'optional string|null',
        'down' => 'optional int',
      ));

    $config = $config + array(
      'argv' => array(),
      'load' => array(),
      'log' => null,
      'down' => 15,
    );

    $daemon = new self();
    $daemon->properties = $config;
    $daemon->daemonID = $daemon->generateDaemonID();

    return $daemon;
  }

  public function setDaemonPool(PhutilDaemonPool $daemon_pool) {
    $this->pool = $daemon_pool;
    return $this;
  }

  public function getDaemonPool() {
    return $this->pool;
  }

  public function getBusyEpoch() {
    return $this->busyEpoch;
  }

  public function getDaemonClass() {
    return $this->getProperty('class');
  }

  private function getProperty($key) {
    return idx($this->properties, $key);
  }

  public function setCommandLineArguments(array $arguments) {
    $this->argv = $arguments;
    return $this;
  }

  public function getCommandLineArguments() {
    return $this->argv;
  }

  public function getDaemonArguments() {
    return $this->getProperty('argv');
  }

  public function didLaunch() {
    $this->restartAt = time();
    $this->shouldSendExitEvent = true;

    $this->dispatchEvent(
      self::EVENT_DID_LAUNCH,
      array(
        'argv' => $this->getCommandLineArguments(),
        'explicitArgv' => $this->getDaemonArguments(),
      ));

    return $this;
  }

  public function isRunning() {
    return (bool)$this->getFuture();
  }

  public function isHibernating() {
    return
      !$this->isRunning() &&
      !$this->isDone() &&
      $this->hibernating;
  }

  public function wakeFromHibernation() {
    if (!$this->isHibernating()) {
      return $this;
    }

    $this->logMessage(
      'WAKE',
      pht(
        'Process is being awakened from hibernation.'));

    $this->restartAt = time();
    $this->update();

    return $this;
  }

  public function isDone() {
    return (!$this->shouldRestart && !$this->isRunning());
  }

  public function update() {
    if (!$this->isRunning()) {
      if (!$this->shouldRestart) {
        return;
      }
      if (!$this->restartAt || (time() < $this->restartAt)) {
        return;
      }
      if ($this->shouldShutdown) {
        return;
      }
      $this->startDaemonProcess();
    }

    $future = $this->getFuture();

    $result = null;
    $caught = null;
    if ($future->canResolve()) {
      $this->future = null;
      try {
        $result = $future->resolve();
      } catch (Exception $ex) {
        $caught = $ex;
      } catch (Throwable $ex) {
        $caught = $ex;
      }
    }

    list($stdout, $stderr) = $future->read();
    $future->discardBuffers();

    if (strlen($stdout)) {
      $this->didReadStdout($stdout);
    }

    $stderr = trim($stderr);
    if (strlen($stderr)) {
      foreach (phutil_split_lines($stderr, false) as $line) {
        $this->logMessage('STDE', $line);
      }
    }

    if ($result !== null || $caught !== null) {

      if ($caught) {
        $message = pht(
          'Process failed with exception: %s',
          $caught->getMessage());
        $this->logMessage('FAIL', $message);
      } else {
        list($err) = $result;

        if ($err) {
          $this->logMessage('FAIL', pht('Process exited with error %s.', $err));
        } else {
          $this->logMessage('DONE', pht('Process exited normally.'));
        }
      }

      if ($this->shouldShutdown) {
        $this->restartAt = null;
      } else {
        $this->scheduleRestart();
      }
    }

    $this->updateHeartbeatEvent();
    $this->updateHangDetection();
  }

  private function updateHeartbeatEvent() {
    if ($this->heartbeat > time()) {
      return;
    }

    $this->heartbeat = time() + $this->getHeartbeatEventFrequency();
    $this->dispatchEvent(self::EVENT_DID_HEARTBEAT);
  }

  private function updateHangDetection() {
    if (!$this->isRunning()) {
      return;
    }

    if (time() > $this->deadline) {
      $this->logMessage('HANG', pht('Hang detected. Restarting process.'));
      $this->annihilateProcessGroup();
      $this->scheduleRestart();
    }
  }

  private function scheduleRestart() {
    // Wait a minimum of a few sceconds before restarting, but we may wait
    // longer if the daemon has initiated hibernation.
    $default_restart = time() + self::getWaitBeforeRestart();
    if ($default_restart >= $this->restartAt) {
      $this->restartAt = $default_restart;
    }

    $this->logMessage(
      'WAIT',
      pht(
        'Waiting %s second(s) to restart process.',
        new PhutilNumber($this->restartAt - time())));
  }

  /**
   * Generate a unique ID for this daemon.
   *
   * @return string A unique daemon ID.
   */
  private function generateDaemonID() {
    return substr(getmypid().':'.Filesystem::readRandomCharacters(12), 0, 12);
  }

  public function getDaemonID() {
    return $this->daemonID;
  }

  private function getFuture() {
    return $this->future;
  }

  private function getPID() {
    $future = $this->getFuture();

    if (!$future) {
      return null;
    }

    if (!$future->hasPID()) {
      return null;
    }

    return $future->getPID();
  }

  private function getCaptureBufferSize() {
    return 65535;
  }

  private function getRequiredHeartbeatFrequency() {
    return 86400;
  }

  public static function getWaitBeforeRestart() {
    return 5;
  }

  public static function getHeartbeatEventFrequency() {
    return 120;
  }

  private function getKillDelay() {
    return 3;
  }

  private function getDaemonCWD() {
    $root = dirname(phutil_get_library_root('phabricator'));
    return $root.'/scripts/daemon/exec/';
  }

  private function newExecFuture() {
    $class = $this->getDaemonClass();
    $argv = $this->getCommandLineArguments();
    $buffer_size = $this->getCaptureBufferSize();

    // NOTE: PHP implements proc_open() by running 'sh -c'. On most systems this
    // is bash, but on Ubuntu it's dash. When you proc_open() using bash, you
    // get one new process (the command you ran). When you proc_open() using
    // dash, you get two new processes: the command you ran and a parent
    // "dash -c" (or "sh -c") process. This means that the child process's PID
    // is actually the 'dash' PID, not the command's PID. To avoid this, use
    // 'exec' to replace the shell process with the real process; without this,
    // the child will call posix_getppid(), be given the pid of the 'sh -c'
    // process, and send it SIGUSR1 to keepalive which will terminate it
    // immediately. We also won't be able to do process group management because
    // the shell process won't properly posix_setsid() so the pgid of the child
    // won't be meaningful.

    $config = $this->properties;
    unset($config['class']);
    $config = phutil_json_encode($config);

    return id(new ExecFuture('exec ./exec_daemon.php %s %Ls', $class, $argv))
      ->setCWD($this->getDaemonCWD())
      ->setStdoutSizeLimit($buffer_size)
      ->setStderrSizeLimit($buffer_size)
      ->write($config);
  }

  /**
   * Dispatch an event to event listeners.
   *
   * @param  string Event type.
   * @param  dict   Event parameters.
   * @return void
   */
  private function dispatchEvent($type, array $params = array()) {
    $data = array(
      'id' => $this->getDaemonID(),
      'daemonClass' => $this->getDaemonClass(),
      'childPID' => $this->getPID(),
    ) + $params;

    $event = new PhutilEvent($type, $data);

    try {
      PhutilEventEngine::dispatchEvent($event);
    } catch (Exception $ex) {
      phlog($ex);
    }
  }

  private function annihilateProcessGroup() {
    $pid = $this->getPID();
    if ($pid) {
      $pgid = posix_getpgid($pid);
      if ($pgid) {
        posix_kill(-$pgid, SIGTERM);
        sleep($this->getKillDelay());
        posix_kill(-$pgid, SIGKILL);
      }
    }
  }

  private function startDaemonProcess() {
    $this->logMessage('INIT', pht('Starting process.'));

    $this->deadline = time() + $this->getRequiredHeartbeatFrequency();
    $this->heartbeat = time() + self::getHeartbeatEventFrequency();
    $this->stdoutBuffer = '';
    $this->hibernating = false;

    $future = $this->newExecFuture();
    $this->future = $future;

    $pool = $this->getDaemonPool();
    $overseer = $pool->getOverseer();
    $overseer->addFutureToPool($future);
  }

  private function didReadStdout($data) {
    $this->stdoutBuffer .= $data;
    while (true) {
      $pos = strpos($this->stdoutBuffer, "\n");
      if ($pos === false) {
        break;
      }
      $message = substr($this->stdoutBuffer, 0, $pos);
      $this->stdoutBuffer = substr($this->stdoutBuffer, $pos + 1);

      try {
        $structure = phutil_json_decode($message);
      } catch (PhutilJSONParserException $ex) {
        $structure = array();
      }

      switch (idx($structure, 0)) {
        case PhutilDaemon::MESSAGETYPE_STDOUT:
          $this->logMessage('STDO', idx($structure, 1));
          break;
        case PhutilDaemon::MESSAGETYPE_HEARTBEAT:
          $this->deadline = time() + $this->getRequiredHeartbeatFrequency();
          break;
        case PhutilDaemon::MESSAGETYPE_BUSY:
          if (!$this->busyEpoch) {
            $this->busyEpoch = time();
          }
          break;
        case PhutilDaemon::MESSAGETYPE_IDLE:
          $this->busyEpoch = null;
          break;
        case PhutilDaemon::MESSAGETYPE_DOWN:
          // The daemon is exiting because it doesn't have enough work and it
          // is trying to scale the pool down. We should not restart it.
          $this->shouldRestart = false;
          $this->shouldShutdown = true;
          break;
        case PhutilDaemon::MESSAGETYPE_HIBERNATE:
          $config = idx($structure, 1);
          $duration = (int)idx($config, 'duration', 0);
          $this->restartAt = time() + $duration;
          $this->hibernating = true;
          $this->busyEpoch = null;
          $this->logMessage(
            'ZZZZ',
            pht(
              'Process is preparing to hibernate for %s second(s).',
              new PhutilNumber($duration)));
          break;
        default:
          // If we can't parse this or it isn't a message we understand, just
          // emit the raw message.
          $this->logMessage('STDO', pht('<Malformed> %s', $message));
          break;
      }
    }
  }

  public function didReceiveNotifySignal($signo) {
    $pid = $this->getPID();
    if ($pid) {
      posix_kill($pid, $signo);
    }
  }

  public function didReceiveReloadSignal($signo) {
    $signame = phutil_get_signal_name($signo);
    if ($signame) {
      $sigmsg = pht(
        'Reloading in response to signal %d (%s).',
        $signo,
        $signame);
    } else {
      $sigmsg = pht(
        'Reloading in response to signal %d.',
        $signo);
    }

    $this->logMessage('RELO', $sigmsg, $signo);

    // This signal means "stop the current process gracefully, then launch
    // a new identical process once it exits". This can be used to update
    // daemons after code changes (the new processes will run the new code)
    // without aborting any running tasks.

    // We SIGINT the daemon but don't set the shutdown flag, so it will
    // naturally be restarted after it exits, as though it had exited after an
    // unhandled exception.

    $pid = $this->getPID();
    if ($pid) {
      posix_kill($pid, SIGINT);
    }
  }

  public function didReceiveGracefulSignal($signo) {
    $this->shouldShutdown = true;
    $this->shouldRestart = false;

    $signame = phutil_get_signal_name($signo);
    if ($signame) {
      $sigmsg = pht(
        'Graceful shutdown in response to signal %d (%s).',
        $signo,
        $signame);
    } else {
      $sigmsg = pht(
        'Graceful shutdown in response to signal %d.',
        $signo);
    }

    $this->logMessage('DONE', $sigmsg, $signo);

    $pid = $this->getPID();
    if ($pid) {
      posix_kill($pid, SIGINT);
    }
  }

  public function didReceiveTerminateSignal($signo) {
    $this->shouldShutdown = true;
    $this->shouldRestart = false;

    $signame = phutil_get_signal_name($signo);
    if ($signame) {
      $sigmsg = pht(
        'Shutting down in response to signal %s (%s).',
        $signo,
        $signame);
    } else {
      $sigmsg = pht('Shutting down in response to signal %s.', $signo);
    }

    $this->logMessage('EXIT', $sigmsg, $signo);
    $this->annihilateProcessGroup();
  }

  private function logMessage($type, $message, $context = null) {
    $this->getDaemonPool()->logMessage($type, $message, $context);

    $this->dispatchEvent(
      self::EVENT_DID_LOG,
      array(
        'type' => $type,
        'message' => $message,
        'context' => $context,
      ));
  }

  public function didExit() {
    if ($this->shouldSendExitEvent) {
      $this->dispatchEvent(self::EVENT_WILL_EXIT);
      $this->shouldSendExitEvent = false;
    }

    return $this;
  }

}
