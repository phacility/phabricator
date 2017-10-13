<?php

final class PhabricatorClientConnectionLimit
  extends PhabricatorClientLimit {

  protected function getBucketDuration() {
    return 60;
  }

  protected function getBucketCount() {
    return 15;
  }

  protected function shouldRejectConnection($score) {
    // Reject connections if the cumulative score across all buckets exceeds
    // the limit.
    return ($score > $this->getLimit());
  }

  protected function getConnectScore() {
    return 1;
  }

  protected function getPenaltyScore() {
    return 0;
  }

  protected function getDisconnectScore(array $request_state) {
    return -1;
  }

  protected function getRateLimitReason($score) {
    $client_key = $this->getClientKey();

    // NOTE: This happens before we load libraries, so we can not use pht()
    // here.

    return
      "TOO MANY CONCURRENT CONNECTIONS\n".
      "You (\"{$client_key}\") have too many concurrent ".
      "connections.\n";
  }

}
