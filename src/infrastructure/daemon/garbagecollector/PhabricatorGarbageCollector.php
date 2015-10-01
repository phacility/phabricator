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
   * Collect garbage from whatever source this GC handles.
   *
   * @return bool True if there is more garbage to collect.
   * @task collect
   */
  abstract public function collectGarbage();


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
