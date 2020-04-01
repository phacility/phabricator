<?php

final class PhutilKeyValueCacheProfiler extends PhutilKeyValueCacheProxy {

  private $profiler;
  private $name;

  public function setName($name) {
    $this->name = $name;
    return $this;
  }

  public function getName() {
    return $this->name;
  }

  /**
   * Set a profiler for cache operations.
   *
   * @param PhutilServiceProfiler Service profiler.
   * @return this
   * @task kvimpl
   */
  public function setProfiler(PhutilServiceProfiler $profiler) {
    $this->profiler = $profiler;
    return $this;
  }


  /**
   * Get the current profiler.
   *
   * @return PhutilServiceProfiler|null Profiler, or null if none is set.
   * @task kvimpl
   */
  public function getProfiler() {
    return $this->profiler;
  }


  public function getKeys(array $keys) {
    $call_id = null;
    if ($this->getProfiler()) {
      $call_id = $this->getProfiler()->beginServiceCall(
        array(
          'type' => 'kvcache-get',
          'name' => $this->getName(),
          'keys' => $keys,
        ));
    }

    $results = parent::getKeys($keys);

    if ($call_id !== null) {
      $this->getProfiler()->endServiceCall(
        $call_id,
        array(
          'hits' => array_keys($results),
        ));
    }

    return $results;
  }


  public function setKeys(array $keys, $ttl = null) {
    $call_id = null;
    if ($this->getProfiler()) {
      $call_id = $this->getProfiler()->beginServiceCall(
        array(
          'type' => 'kvcache-set',
          'name' => $this->getName(),
          'keys' => array_keys($keys),
          'ttl'  => $ttl,
        ));
    }

    $result = parent::setKeys($keys, $ttl);

    if ($call_id !== null) {
      $this->getProfiler()->endServiceCall($call_id, array());
    }

    return $result;
  }


  public function deleteKeys(array $keys) {
    $call_id = null;
    if ($this->getProfiler()) {
      $call_id = $this->getProfiler()->beginServiceCall(
        array(
          'type' => 'kvcache-del',
          'name' => $this->getName(),
          'keys' => $keys,
        ));
    }

    $result = parent::deleteKeys($keys);

    if ($call_id !== null) {
      $this->getProfiler()->endServiceCall($call_id, array());
    }

    return $result;
  }

}
