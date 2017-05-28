<?php

class PhabricatorClusterServiceHealthRecord
  extends Phobject {

  private $cacheKey;
  private $shouldCheck;
  private $isHealthy;
  private $upEventCount;
  private $downEventCount;

  public function __construct($cache_key) {
    $this->cacheKey = $cache_key;
    $this->readState();
  }

  /**
   * Is the database currently healthy?
   */
  public function getIsHealthy() {
    return $this->isHealthy;
  }


  /**
   * Should this request check database health?
   */
  public function getShouldCheck() {
    return $this->shouldCheck;
  }


  /**
   * How many recent health checks were successful?
   */
  public function getUpEventCount() {
    return $this->upEventCount;
  }


  /**
   * How many recent health checks failed?
   */
  public function getDownEventCount() {
    return $this->downEventCount;
  }


  /**
   * Number of failures or successes we need to see in a row before we change
   * the state.
   */
  public function getRequiredEventCount() {
    // NOTE: If you change this value, update the "Cluster: Databases" docs.
    return 5;
  }


  /**
   * Seconds to wait between health checks.
   */
  public function getHealthCheckFrequency() {
    // NOTE: If you change this value, update the "Cluster: Databases" docs.
    return 3;
  }


  public function didHealthCheck($result) {
    $now = microtime(true);
    $check_frequency = $this->getHealthCheckFrequency();
    $event_count = $this->getRequiredEventCount();

    $record = $this->readHealthRecord();

    $log = $record['log'];
    foreach ($log as $key => $event) {
      $when = idx($event, 'timestamp');

      // If the log already has another nearby event, just ignore this one.
      // We raced with another process and our result can just be thrown away.
      if (($now - $when) <= $check_frequency) {
        return $this;
      }
    }

    $log[] = array(
      'timestamp' => $now,
      'up' => $result,
    );

    // Throw away older events which are now obsolete.
    $log = array_slice($log, -$event_count);

    $count_up = 0;
    $count_down = 0;
    foreach ($log as $event) {
      if ($event['up']) {
        $count_up++;
      } else {
        $count_down++;
      }
    }

    // If all of the events are the same, change the state.
    if ($count_up == $event_count) {
      $record['up'] = true;
    } else if ($count_down == $event_count) {
      $record['up'] = false;
    }

    $record['log'] = $log;

    $this->writeHealthRecord($record);

    $this->isHealthy = $record['up'];
    $this->shouldCheck = false;
    $this->updateStatistics($record);

    return $this;
  }


  private function readState() {
    $now = microtime(true);
    $check_frequency = $this->getHealthCheckFrequency();

    $record = $this->readHealthRecord();

    $last_check = $record['lastCheck'];

    if (($now - $last_check) >= $check_frequency) {
      $record['lastCheck'] = $now;
      $this->writeHealthRecord($record);
      $this->shouldCheck = true;
    } else {
      $this->shouldCheck = false;
    }

    $this->isHealthy = $record['up'];
    $this->updateStatistics($record);
  }

  private function updateStatistics(array $record) {
    $this->upEventCount = 0;
    $this->downEventCount = 0;
    foreach ($record['log'] as $event) {
      if ($event['up']) {
        $this->upEventCount++;
      } else {
        $this->downEventCount++;
      }
    }
  }

  public function getCacheKey() {
    return $this->cacheKey;
  }

  private function readHealthRecord() {
    $cache = PhabricatorCaches::getSetupCache();
    $cache_key = $this->getCacheKey();
    $health_record = $cache->getKey($cache_key);

    if (!is_array($health_record)) {
      $health_record = array(
        'up' => true,
        'lastCheck' => 0,
        'log' => array(),
      );
    }

    return $health_record;
  }

  private function writeHealthRecord(array $record) {
    $cache = PhabricatorCaches::getSetupCache();
    $cache_key = $this->getCacheKey();
    $cache->setKey($cache_key, $record);
  }

}
