<?php

final class PhabricatorClientRateLimit
  extends PhabricatorClientLimit {

  protected function getBucketDuration() {
    return 60;
  }

  protected function getBucketCount() {
    return 5;
  }

  protected function shouldRejectConnection($score) {
    $limit = $this->getLimit();

    // Reject connections if the average score across all buckets exceeds the
    // limit.
    $average_score = $score / $this->getBucketCount();

    return ($average_score > $limit);
  }

  protected function getConnectScore() {
    return 0;
  }

  protected function getPenaltyScore() {
    return 1;
  }

  protected function getDisconnectScore(array $request_state) {
    $score = 1;

    // If the user was logged in, let them make more requests.
    if (isset($request_state['viewer'])) {
      $viewer = $request_state['viewer'];
      if ($viewer->isLoggedIn()) {
        $score = 0.25;
      }
    }

    return $score;
  }

  protected function getRateLimitReason($score) {
    $client_key = $this->getClientKey();

    // NOTE: This happens before we load libraries, so we can not use pht()
    // here.

    return
      "TOO MANY REQUESTS\n".
      "You (\"{$client_key}\") are issuing too many requests ".
      "too quickly.\n";
  }

}
