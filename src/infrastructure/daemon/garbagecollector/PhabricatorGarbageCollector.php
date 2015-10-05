<?php

/**
 * @task info Getting Collector Information
 * @task collect Collecting Garbage
 */
abstract class PhabricatorGarbageCollector extends Phobject {


/* -(  Getting Collector Information  )-------------------------------------- */


  /**
   * Get a human readable name for what this collector cleans up, like
   * "User Activity Logs".
   *
   * @return string Human-readable collector name.
   * @task info
   */
  abstract public function getCollectorName();


  /**
   * Specify that the collector has an automatic retention policy and
   * is not configurable.
   *
   * @return bool True if the collector has an automatic retention policy.
   * @task info
   */
  public function hasAutomaticPolicy() {
    return false;
  }


  /**
   * Get the default retention policy for this collector.
   *
   * Return the age (in seconds) when resources start getting collected, or
   * `null` to retain resources indefinitely.
   *
   * @return int|null Lifetime, or `null` for indefinite retention.
   * @task info
   */
  public function getDefaultRetentionPolicy() {
    throw new PhutilMethodNotImplementedException();
  }


  /**
   * Get the effective retention policy.
   *
   * @return int|null Lifetime, or `null` for indefinite retention.
   * @task info
   */
  public function getRetentionPolicy() {
    if ($this->hasAutomaticPolicy()) {
      throw new Exception(
        pht(
          'Can not get retention policy of collector with automatic '.
          'policy.'));
    }

    $config = PhabricatorEnv::getEnvConfig('phd.garbage-collection');
    $const = $this->getCollectorConstant();

    return idx($config, $const, $this->getDefaultRetentionPolicy());
  }



  /**
   * Get a unique string constant identifying this collector.
   *
   * @return string Collector constant.
   * @task info
   */
  final public function getCollectorConstant() {
    return $this->getPhobjectClassConstant('COLLECTORCONST', 64);
  }


/* -(  Collecting Garbage  )------------------------------------------------- */


  /**
   * Run the collector.
   *
   * @return bool True if there is more garbage to collect.
   * @task collect
   */
  final public function runCollector() {
    // Don't do anything if this collector is configured with an indefinite
    // retention policy.
    if (!$this->hasAutomaticPolicy()) {
      $policy = $this->getRetentionPolicy();
      if (!$policy) {
        return false;
      }
    }

    return $this->collectGarbage();
  }


  /**
   * Collect garbage from whatever source this GC handles.
   *
   * @return bool True if there is more garbage to collect.
   * @task collect
   */
  abstract protected function collectGarbage();


  /**
   * Get the most recent epoch timestamp that is considered garbage.
   *
   * Records older than this should be collected.
   *
   * @return int Most recent garbage timestamp.
   * @task collect
   */
  final protected function getGarbageEpoch() {
    if ($this->hasAutomaticPolicy()) {
      throw new Exception(
        pht(
          'Can not get garbage epoch for a collector with an automatic '.
          'collection policy.'));
    }

    $ttl = $this->getRetentionPolicy();
    if (!$ttl) {
      throw new Exception(
        pht(
          'Can not get garbage epoch for a collector with an indefinite '.
          'retention policy.'));
    }

    return (PhabricatorTime::getNow() - $ttl);
  }


  /**
   * Load all of the available garbage collectors.
   *
   * @return list<PhabricatorGarbageCollector> Garbage collectors.
   * @task collect
   */
  final public static function getAllCollectors() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getCollectorConstant')
      ->execute();
  }

}
