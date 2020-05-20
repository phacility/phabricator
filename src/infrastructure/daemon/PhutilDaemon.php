<?php

/**
 * Scaffolding for implementing robust background processing scripts.
 *
 *
 * Autoscaling
 * ===========
 *
 * Autoscaling automatically launches copies of a daemon when it is busy
 * (scaling the pool up) and stops them when they're idle (scaling the pool
 * down). This is appropriate for daemons which perform highly parallelizable
 * work.
 *
 * To make a daemon support autoscaling, the implementation should look
 * something like this:
 *
 *   while (!$this->shouldExit()) {
 *     if (work_available()) {
 *       $this->willBeginWork();
 *       do_work();
 *       $this->sleep(0);
 *     } else {
 *       $this->willBeginIdle();
 *       $this->sleep(1);
 *     }
 *   }
 *
 * In particular, call @{method:willBeginWork} before becoming busy, and
 * @{method:willBeginIdle} when no work is available. If the daemon is launched
 * into an autoscale pool, this will cause the pool to automatically scale up
 * when busy and down when idle.
 *
 * See @{class:PhutilHighIntensityIntervalDaemon} for an example of a simple
 * autoscaling daemon.
 *
 * Launching a daemon which does not make these callbacks into an autoscale
 * pool will have no effect.
 *
 * @task overseer Communicating With the Overseer
 * @task autoscale Autoscaling Daemon Pools
 */
abstract class PhutilDaemon extends Phobject {

  const MESSAGETYPE_STDOUT = 'stdout';
  const MESSAGETYPE_HEARTBEAT = 'heartbeat';
  const MESSAGETYPE_BUSY = 'busy';
  const MESSAGETYPE_IDLE = 'idle';
  const MESSAGETYPE_DOWN = 'down';
  const MESSAGETYPE_HIBERNATE = 'hibernate';

  const WORKSTATE_BUSY = 'busy';
  const WORKSTATE_IDLE = 'idle';

  private $argv;
  private $traceMode;
  private $traceMemory;
  private $verbose;
  private $notifyReceived;
  private $inGracefulShutdown;
  private $workState = null;
  private $idleSince = null;
  private $scaledownDuration;

  final public function setVerbose($verbose) {
    $this->verbose = $verbose;
    return $this;
  }

  final public function getVerbose() {
    return $this->verbose;
  }

  final public function setScaledownDuration($scaledown_duration) {
    $this->scaledownDuration = $scaledown_duration;
    return $this;
  }

  final public function getScaledownDuration() {
    return $this->scaledownDuration;
  }

  final public function __construct(array $argv) {
    $this->argv = $argv;

    $router = PhutilSignalRouter::getRouter();
    $handler_key = 'daemon.term';
    if (!$router->getHandler($handler_key)) {
      $handler = new PhutilCallbackSignalHandler(
        SIGTERM,
        __CLASS__.'::onTermSignal');
      $router->installHandler($handler_key, $handler);
    }

    pcntl_signal(SIGINT, array($this, 'onGracefulSignal'));
    pcntl_signal(SIGUSR2, array($this, 'onNotifySignal'));

    // Without discard mode, this consumes unbounded amounts of memory. Keep
    // memory bounded.
    PhutilServiceProfiler::getInstance()->enableDiscardMode();

    $this->beginStdoutCapture();
  }

  final public function __destruct() {
    $this->endStdoutCapture();
  }

  final public function stillWorking() {
    $this->emitOverseerMessage(self::MESSAGETYPE_HEARTBEAT, null);

    if ($this->traceMemory) {
      $daemon = get_class($this);
      fprintf(
        STDERR,
        "%s %s %s\n",
        '<RAMS>',
        $daemon,
        pht(
          'Memory Usage: %s KB',
          new PhutilNumber(memory_get_usage() / 1024, 1)));
    }
  }

  final public function shouldExit() {
    return $this->inGracefulShutdown;
  }

  final protected function shouldHibernate($duration) {
    // Don't hibernate if we don't have very long to sleep.
    if ($duration < 30) {
      return false;
    }

    // Never hibernate if we're part of a pool and could scale down instead.
    // We only hibernate the last process to drop the pool size to zero.
    if ($this->getScaledownDuration()) {
      return false;
    }

    // Don't hibernate for too long.
    $duration = min($duration, phutil_units('3 minutes in seconds'));

    $this->emitOverseerMessage(
      self::MESSAGETYPE_HIBERNATE,
      array(
        'duration' => $duration,
      ));

    $this->log(
      pht(
        'Preparing to hibernate for %s second(s).',
        new PhutilNumber($duration)));

    return true;
  }

  final protected function sleep($duration) {
    $this->notifyReceived = false;
    $this->willSleep($duration);
    $this->stillWorking();

    $scale_down = $this->getScaledownDuration();

    $max_sleep = 60;
    if ($scale_down) {
      $max_sleep = min($max_sleep, $scale_down);
    }

    if ($scale_down) {
      if ($this->workState == self::WORKSTATE_IDLE) {
        $dur = $this->getIdleDuration();
        $this->log(pht('Idle for %s seconds.', $dur));
      }
    }

    while ($duration > 0 &&
      !$this->notifyReceived &&
      !$this->shouldExit()) {

      // If this is an autoscaling clone and we've been idle for too long,
      // we're going to scale the pool down by exiting and not restarting. The
      // DOWN message tells the overseer that we don't want to be restarted.
      if ($scale_down) {
        if ($this->workState == self::WORKSTATE_IDLE) {
          if ($this->idleSince && ($this->idleSince + $scale_down < time())) {
            $this->inGracefulShutdown = true;
            $this->emitOverseerMessage(self::MESSAGETYPE_DOWN, null);
            $this->log(
              pht(
                'Daemon was idle for more than %s second(s), '.
                'scaling pool down.',
                new PhutilNumber($scale_down)));
            break;
          }
        }
      }

      sleep(min($duration, $max_sleep));
      $duration -= $max_sleep;
      $this->stillWorking();
    }
  }

  protected function willSleep($duration) {
    return;
  }

  public static function onTermSignal($signo) {
    self::didCatchSignal($signo);
  }

  final protected function getArgv() {
    return $this->argv;
  }

  final public function execute() {
    $this->willRun();
    $this->run();
  }

  abstract protected function run();

  final public function setTraceMemory() {
    $this->traceMemory = true;
    return $this;
  }

  final public function getTraceMemory() {
    return $this->traceMemory;
  }

  final public function setTraceMode() {
    $this->traceMode = true;
    PhutilServiceProfiler::installEchoListener();
    PhutilConsole::getConsole()->getServer()->setEnableLog(true);
    $this->didSetTraceMode();
    return $this;
  }

  final public function getTraceMode() {
    return $this->traceMode;
  }

  final public function onGracefulSignal($signo) {
    self::didCatchSignal($signo);
    $this->inGracefulShutdown = true;
  }

  final public function onNotifySignal($signo) {
    self::didCatchSignal($signo);
    $this->notifyReceived = true;
    $this->onNotify($signo);
  }

  protected function onNotify($signo) {
    // This is a hook for subclasses.
  }

  protected function willRun() {
    // This is a hook for subclasses.
  }

  protected function didSetTraceMode() {
    // This is a hook for subclasses.
  }

  final protected function log($message) {
    if ($this->verbose) {
      $daemon = get_class($this);
      fprintf(STDERR, "%s %s %s\n", '<VERB>', $daemon, $message);
    }
  }

  private static function didCatchSignal($signo) {
    $signame = phutil_get_signal_name($signo);
    fprintf(
      STDERR,
      "%s Caught signal %s (%s).\n",
      '<SGNL>',
      $signo,
      $signame);
  }


/* -(  Communicating With the Overseer  )------------------------------------ */


  private function beginStdoutCapture() {
    ob_start(array($this, 'didReceiveStdout'), 2);
  }

  private function endStdoutCapture() {
    ob_end_flush();
  }

  public function didReceiveStdout($data) {
    if (!strlen($data)) {
      return '';
    }

    return $this->encodeOverseerMessage(self::MESSAGETYPE_STDOUT, $data);
  }

  private function encodeOverseerMessage($type, $data) {
    $structure = array($type);

    if ($data !== null) {
      $structure[] = $data;
    }

    return json_encode($structure)."\n";
  }

  private function emitOverseerMessage($type, $data) {
    $this->endStdoutCapture();
    echo $this->encodeOverseerMessage($type, $data);
    $this->beginStdoutCapture();
  }

  public static function errorListener($event, $value, array $metadata) {
    // If the caller has redirected the error log to a file, PHP won't output
    // messages to stderr, so the overseer can't capture them. Install a
    // listener which just  echoes errors to stderr, so the overseer is always
    // aware of errors.

    $console = PhutilConsole::getConsole();
    $message = idx($metadata, 'default_message');

    if ($message) {
      $console->writeErr("%s\n", $message);
    }
    if (idx($metadata, 'trace')) {
      $trace = PhutilErrorHandler::formatStacktrace($metadata['trace']);
      $console->writeErr("%s\n", $trace);
    }
  }


/* -(  Autoscaling  )-------------------------------------------------------- */


  /**
   * Prepare to become busy. This may autoscale the pool up.
   *
   * This notifies the overseer that the daemon has become busy. If daemons
   * that are part of an autoscale pool are continuously busy for a prolonged
   * period of time, the overseer may scale up the pool.
   *
   * @return this
   * @task autoscale
   */
  protected function willBeginWork() {
    if ($this->workState != self::WORKSTATE_BUSY) {
      $this->workState = self::WORKSTATE_BUSY;
      $this->idleSince = null;
      $this->emitOverseerMessage(self::MESSAGETYPE_BUSY, null);
    }

    return $this;
  }


  /**
   * Prepare to idle. This may autoscale the pool down.
   *
   * This notifies the overseer that the daemon is no longer busy. If daemons
   * that are part of an autoscale pool are idle for a prolonged period of
   * time, they may exit to scale the pool down.
   *
   * @return this
   * @task autoscale
   */
  protected function willBeginIdle() {
    if ($this->workState != self::WORKSTATE_IDLE) {
      $this->workState = self::WORKSTATE_IDLE;
      $this->idleSince = time();
      $this->emitOverseerMessage(self::MESSAGETYPE_IDLE, null);
    }

    return $this;
  }

  protected function getIdleDuration() {
    if (!$this->idleSince) {
      return null;
    }

    $now = time();
    return ($now - $this->idleSince);
  }

}
