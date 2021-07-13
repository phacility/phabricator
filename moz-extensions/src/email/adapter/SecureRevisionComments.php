<?php


class SecureRevisionComments {
  public int $count;
  public SecureEventPings $pings;

  public function __construct(int $commentCount, SecureEventPings $pings) {
    $this->count = $commentCount;
    $this->pings = $pings;
  }


}