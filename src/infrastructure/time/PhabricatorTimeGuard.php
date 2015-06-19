<?php

final class PhabricatorTimeGuard extends Phobject {

  private $frameKey;

  public function __construct($frame_key) {
    $this->frameKey = $frame_key;
  }

  public function __destruct() {
    PhabricatorTime::popTime($this->frameKey);
  }

}
