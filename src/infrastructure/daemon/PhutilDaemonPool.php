<?php

final class PhutilDaemonPool extends Phobject {

  private $properties = array();
  private $commandLineArguments;

  private $overseer;
  private $daemons = array();
  private $argv;

  private $lastAutoscaleUpdate;
  private $inShutdown;

  private function __construct() {
    // <empty>
  }

  public static function newFromConfig(array $config) {
    PhutilTypeSpec::checkMap(
      $config,
      array(
        'class' => 'string',
        'label' => 'string',
        'argv' => 'optional list<string>',
        'load' => 'optional list<string>',
        'log' => 'optional string|null',
        'pool' => 'optional int',
        'up' => 'optional int',
        'down' => 'optional int',
        'reserve' => 'optional int|float',
      ));

    $config = $config + array(
      'argv' => array(),
      'load' => array(),
      'log' => null,
      'pool' => 1,
      'up' => 2,
      'down' => 15,
      'reserve' => 0,
    );

    $pool = new self();
    $pool->properties = $config;

    return $pool;
  }

  public function setOverseer(PhutilDaemonOverseer $overseer) {
    $this->overseer = $overseer;
    return $this;
  }

  public function getOverseer() {
    return $this->overseer;
  }

  public function setCommandLineArguments(array $arguments) {
    $this->commandLineArguments = $arguments;
    return $this;
  }

  public function getCommandLineArguments() {
    return $this->commandLineArguments;
  }

  private function shouldShutdown() {
    return $this->inShutdown;
  }

  private function newDaemon() {
    $config = $this->properties;

    if (count($this->daemons)) {
      $down_duration = $this->getPoolScaledownDuration();
    } else {
      // TODO: For now, never scale pools down to 0.
      $down_duration = 0;
    }

    $forced_config = array(
      'down' => $down_duration,
    );

    $config = $forced_config + $config;

    $config = array_select_keys(
      $config,
      array(
        'class',
        'log',
        'load',
        'argv',
        'down',
      ));

    $daemon = PhutilDaemonHandle::newFromConfig($config)
      ->setDaemonPool($this)
      ->setCommandLineArguments($this->getCommandLineArguments());

    $daemon_id = $daemon->getDaemonID();
    $this->daemons[$daemon_id] = $daemon;

    $daemon->didLaunch();

    return $daemon;
  }

  public function getDaemons() {
    return $this->daemons;
  }

  public function didReceiveSignal($signal, $signo) {
    switch ($signal) {
      case PhutilDaemonOverseer::SIGNAL_GRACEFUL:
      case PhutilDaemonOverseer::SIGNAL_TERMINATE:
        $this->inShutdown = true;
        break;
    }

    foreach ($this->getDaemons() as $daemon) {
      switch ($signal) {
        case PhutilDaemonOverseer::SIGNAL_NOTIFY:
          $daemon->didReceiveNotifySignal($signo);
          break;
        case PhutilDaemonOverseer::SIGNAL_RELOAD:
          $daemon->didReceiveReloadSignal($signo);
          break;
        case PhutilDaemonOverseer::SIGNAL_GRACEFUL:
          $daemon->didReceiveGracefulSignal($signo);
          break;
        case PhutilDaemonOverseer::SIGNAL_TERMINATE:
          $daemon->didReceiveTerminateSignal($signo);
          break;
        default:
          throw new Exception(
            pht(
              'Unknown signal "%s" ("%d").',
              $signal,
              $signo));
      }
    }
  }

  public function getPoolLabel() {
    return $this->getPoolProperty('label');
  }

  public function getPoolMaximumSize() {
    return $this->getPoolProperty('pool');
  }

  public function getPoolScaleupDuration() {
    return $this->getPoolProperty('up');
  }

  public function getPoolScaledownDuration() {
    return $this->getPoolProperty('down');
  }

  public function getPoolMemoryReserve() {
    return $this->getPoolProperty('reserve');
  }

  public function getPoolDaemonClass() {
    return $this->getPoolProperty('class');
  }

  private function getPoolProperty($key) {
    return idx($this->properties, $key);
  }

  public function updatePool() {
    $daemons = $this->getDaemons();

    foreach ($daemons as $key => $daemon) {
      $daemon->update();

      if ($daemon->isDone()) {
        $daemon->didExit();

        unset($this->daemons[$key]);

        if ($this->shouldShutdown()) {
          $this->logMessage(
            'DOWN',
            pht(
              'Pool "%s" is exiting, with %s daemon(s) remaining.',
              $this->getPoolLabel(),
              new PhutilNumber(count($this->daemons))));
        } else {
          $this->logMessage(
            'POOL',
            pht(
              'Autoscale pool "%s" scaled down to %s daemon(s).',
              $this->getPoolLabel(),
              new PhutilNumber(count($this->daemons))));
        }
      }
    }

    $this->updateAutoscale();
  }

  public function isHibernating() {
    foreach ($this->getDaemons() as $daemon) {
      if (!$daemon->isHibernating()) {
        return false;
      }
    }

    return true;
  }

  public function wakeFromHibernation() {
    if (!$this->isHibernating()) {
      return $this;
    }

    $this->logMessage(
      'WAKE',
      pht(
        'Autoscale pool "%s" is being awakened from hibernation.',
        $this->getPoolLabel()));

    $did_wake_daemons = false;
    foreach ($this->getDaemons() as $daemon) {
      if ($daemon->isHibernating()) {
        $daemon->wakeFromHibernation();
        $did_wake_daemons = true;
      }
    }

    if (!$did_wake_daemons) {
      // TODO: Pools currently can't scale down to 0 daemons, but we should
      // scale up immediately here once they can.
    }

    $this->updatePool();

    return $this;
  }

  private function updateAutoscale() {
    if ($this->shouldShutdown()) {
      return;
    }

    // Don't try to autoscale more than once per second. This mostly stops the
    // logs from getting flooded in verbose mode.
    $now = time();
    if ($this->lastAutoscaleUpdate >= $now) {
      return;
    }
    $this->lastAutoscaleUpdate = $now;

    $daemons = $this->getDaemons();

    // If this pool is already at the maximum size, we can't launch any new
    // daemons.
    $max_size = $this->getPoolMaximumSize();
    if (count($daemons) >= $max_size) {
      $this->logMessage(
        'POOL',
        pht(
          'Autoscale pool "%s" already at maximum size (%s of %s).',
          $this->getPoolLabel(),
          new PhutilNumber(count($daemons)),
          new PhutilNumber($max_size)));
      return;
    }

    $scaleup_duration = $this->getPoolScaleupDuration();

    foreach ($daemons as $daemon) {
      $busy_epoch = $daemon->getBusyEpoch();
      // If any daemons haven't started work yet, don't scale the pool up.
      if (!$busy_epoch) {
        $this->logMessage(
          'POOL',
          pht(
            'Autoscale pool "%s" has an idle daemon, declining to scale.',
            $this->getPoolLabel()));
        return;
      }

      // If any daemons started work very recently, wait a little while
      // to scale the pool up.
      $busy_for = ($now - $busy_epoch);
      if ($busy_for < $scaleup_duration) {
        $this->logMessage(
          'POOL',
          pht(
            'Autoscale pool "%s" has not been busy long enough to scale up '.
            '(busy for %s of %s seconds).',
            $this->getPoolLabel(),
            new PhutilNumber($busy_for),
            new PhutilNumber($scaleup_duration)));
        return;
      }
    }

    // If we have a configured memory reserve for this pool, it tells us that
    // we should not scale up unless there's at least that much memory left
    // on the system (for example, a reserve of 0.25 means that 25% of system
    // memory must be free to autoscale).

    // Note that the first daemon is exempt: we'll always launch at least one
    // daemon, regardless of any memory reservation.
    if (count($daemons)) {
      $reserve = $this->getPoolMemoryReserve();
      if ($reserve) {
        // On some systems this may be slightly more expensive than other
        // checks, so we only do it once we're prepared to scale up.
        $memory = PhutilSystem::getSystemMemoryInformation();
        $free_ratio = ($memory['free'] / $memory['total']);

        // If we don't have enough free memory, don't scale.
        if ($free_ratio <= $reserve) {
          $this->logMessage(
            'POOL',
            pht(
              'Autoscale pool "%s" does not have enough free memory to '.
              'scale up (%s free of %s reserved).',
              $this->getPoolLabel(),
              new PhutilNumber($free_ratio, 3),
              new PhutilNumber($reserve, 3)));
          return;
        }
      }
    }

    $this->logMessage(
      'AUTO',
      pht(
        'Scaling pool "%s" up to %s daemon(s).',
        $this->getPoolLabel(),
        new PhutilNumber(count($daemons) + 1)));

    $this->newDaemon();
  }

  public function logMessage($type, $message, $context = null) {
    return $this->getOverseer()->logMessage($type, $message, $context);
  }

}
