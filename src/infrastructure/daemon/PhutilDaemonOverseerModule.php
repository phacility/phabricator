<?php

/**
 * Overseer modules allow daemons to be externally influenced.
 *
 * See @{class:PhabricatorDaemonOverseerModule} for a concrete example.
 */
abstract class PhutilDaemonOverseerModule extends Phobject {

  private $throttles = array();


  /**
   * This method is used to indicate to the overseer that daemons should reload.
   *
   * @return bool  True if the daemons should reload, otherwise false.
   */
  public function shouldReloadDaemons() {
    return false;
  }


  /**
   * Should a hibernating daemon pool be awoken immediately?
   *
   * @return bool True to awaken the pool immediately.
   */
  public function shouldWakePool(PhutilDaemonPool $pool) {
    return false;
  }


  public static function getAllModules() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->execute();
  }


  /**
   * Throttle checks from executing too often.
   *
   * If you throttle a check like this, it will only execute once every 2.5
   * seconds:
   *
   *   if ($this->shouldThrottle('some.check', 2.5)) {
   *     return;
   *   }
   *
   * @param string Throttle key.
   * @param float Duration in seconds.
   * @return bool True to throttle the check.
   */
  protected function shouldThrottle($name, $duration) {
    $throttle = idx($this->throttles, $name, 0);
    $now = microtime(true);

    // If not enough time has elapsed, throttle the check.
    $elapsed = ($now - $throttle);
    if ($elapsed < $duration) {
      return true;
    }

    // Otherwise, mark the current time as the last time we ran the check,
    // then let it continue.
    $this->throttles[$name] = $now;

    return false;
  }

}
