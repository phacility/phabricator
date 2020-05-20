<?php

abstract class PhabricatorClientLimit {

  private $limitKey;
  private $clientKey;
  private $limit;

  final public function setLimitKey($limit_key) {
    $this->limitKey = $limit_key;
    return $this;
  }

  final public function getLimitKey() {
    return $this->limitKey;
  }

  final public function setClientKey($client_key) {
    $this->clientKey = $client_key;
    return $this;
  }

  final public function getClientKey() {
    return $this->clientKey;
  }

  final public function setLimit($limit) {
    $this->limit = $limit;
    return $this;
  }

  final public function getLimit() {
    return $this->limit;
  }

  final public function didConnect() {
    // NOTE: We can not use pht() here because this runs before libraries
    // load.

    if (!function_exists('apc_fetch') && !function_exists('apcu_fetch')) {
      throw new Exception(
        'You can not configure connection rate limits unless APC/APCu are '.
        'available. Rate limits rely on APC/APCu to track clients and '.
        'connections.');
    }

    if ($this->getClientKey() === null) {
      throw new Exception(
        'You must configure a client key when defining a rate limit.');
    }

    if ($this->getLimitKey() === null) {
      throw new Exception(
        'You must configure a limit key when defining a rate limit.');
    }

    if ($this->getLimit() === null) {
      throw new Exception(
        'You must configure a limit when defining a rate limit.');
    }

    $points = $this->getConnectScore();
    if ($points) {
      $this->addScore($points);
    }

    $score = $this->getScore();
    if (!$this->shouldRejectConnection($score)) {
      // Client has not hit the limit, so continue processing the request.
      return null;
    }

    $penalty = $this->getPenaltyScore();
    if ($penalty) {
      $this->addScore($penalty);
      $score += $penalty;
    }

    return $this->getRateLimitReason($score);
  }

  final public function didDisconnect(array $request_state) {
    $score = $this->getDisconnectScore($request_state);
    if ($score) {
      $this->addScore($score);
    }
  }


  /**
   * Get the number of seconds for each rate bucket.
   *
   * For example, a value of 60 will create one-minute buckets.
   *
   * @return int Number of seconds per bucket.
   */
  abstract protected function getBucketDuration();


  /**
   * Get the total number of rate limit buckets to retain.
   *
   * @return int Total number of rate limit buckets to retain.
   */
  abstract protected function getBucketCount();


  /**
   * Get the score to add when a client connects.
   *
   * @return double Connection score.
   */
  abstract protected function getConnectScore();


  /**
   * Get the number of penalty points to add when a client hits a rate limit.
   *
   * @return double Penalty score.
   */
  abstract protected function getPenaltyScore();


  /**
   * Get the score to add when a client disconnects.
   *
   * @return double Connection score.
   */
  abstract protected function getDisconnectScore(array $request_state);


  /**
   * Get a human-readable explanation of why the client is being rejected.
   *
   * @return string Brief rejection message.
   */
  abstract protected function getRateLimitReason($score);


  /**
   * Determine whether to reject a connection.
   *
   * @return bool True to reject the connection.
   */
  abstract protected function shouldRejectConnection($score);


  /**
   * Get the APC key for the smallest stored bucket.
   *
   * @return string APC key for the smallest stored bucket.
   * @task ratelimit
   */
  private function getMinimumBucketCacheKey() {
    $limit_key = $this->getLimitKey();
    return "limit:min:{$limit_key}";
  }


  /**
   * Get the current bucket ID for storing rate limit scores.
   *
   * @return int The current bucket ID.
   */
  private function getCurrentBucketID() {
    return (int)(time() / $this->getBucketDuration());
  }


  /**
   * Get the APC key for a given bucket.
   *
   * @param int Bucket to get the key for.
   * @return string APC key for the bucket.
   */
  private function getBucketCacheKey($bucket_id) {
    $limit_key = $this->getLimitKey();
    return "limit:bucket:{$limit_key}:{$bucket_id}";
  }


  /**
   * Add points to the rate limit score for some client.
   *
   * @param string  Some key which identifies the client making the request.
   * @param float   The cost for this request; more points pushes them toward
   *                the limit faster.
   * @return this
   */
  private function addScore($score) {
    $is_apcu = (bool)function_exists('apcu_fetch');

    $current = $this->getCurrentBucketID();
    $bucket_key = $this->getBucketCacheKey($current);

    // There's a bit of a race here, if a second process reads the bucket
    // before this one writes it, but it's fine if we occasionally fail to
    // record a client's score. If they're making requests fast enough to hit
    // rate limiting, we'll get them soon enough.

    if ($is_apcu) {
      $bucket = apcu_fetch($bucket_key);
    } else {
      $bucket = apc_fetch($bucket_key);
    }

    if (!is_array($bucket)) {
      $bucket = array();
    }

    $client_key = $this->getClientKey();
    if (empty($bucket[$client_key])) {
      $bucket[$client_key] = 0;
    }

    $bucket[$client_key] += $score;

    if ($is_apcu) {
      @apcu_store($bucket_key, $bucket);
    } else {
      @apc_store($bucket_key, $bucket);
    }

    return $this;
  }


  /**
   * Get the current rate limit score for a given client.
   *
   * @return float The client's current score.
   * @task ratelimit
   */
  private function getScore() {
    $is_apcu = (bool)function_exists('apcu_fetch');

    // Identify the oldest bucket stored in APC.
    $min_key = $this->getMinimumBucketCacheKey();
    if ($is_apcu) {
      $min = apcu_fetch($min_key);
    } else {
      $min = apc_fetch($min_key);
    }

    // If we don't have any buckets stored yet, store the current bucket as
    // the oldest bucket.
    $cur = $this->getCurrentBucketID();
    if (!$min) {
      if ($is_apcu) {
        @apcu_store($min_key, $cur);
      } else {
        @apc_store($min_key, $cur);
      }
      $min = $cur;
    }

    // Destroy any buckets that are older than the minimum bucket we're keeping
    // track of. Under load this normally shouldn't do anything, but will clean
    // up an old bucket once per minute.
    $count = $this->getBucketCount();
    for ($cursor = $min; $cursor < ($cur - $count); $cursor++) {
      $bucket_key = $this->getBucketCacheKey($cursor);
      if ($is_apcu) {
        apcu_delete($bucket_key);
        @apcu_store($min_key, $cursor + 1);
      } else {
        apc_delete($bucket_key);
        @apc_store($min_key, $cursor + 1);
      }
    }

    $client_key = $this->getClientKey();

    // Now, sum up the client's scores in all of the active buckets.
    $score = 0;
    for (; $cursor <= $cur; $cursor++) {
      $bucket_key = $this->getBucketCacheKey($cursor);
      if ($is_apcu) {
        $bucket = apcu_fetch($bucket_key);
      } else {
        $bucket = apc_fetch($bucket_key);
      }
      if (isset($bucket[$client_key])) {
        $score += $bucket[$client_key];
      }
    }

    return $score;
  }

}
