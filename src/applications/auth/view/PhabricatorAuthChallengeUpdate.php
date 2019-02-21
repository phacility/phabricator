<?php

final class PhabricatorAuthChallengeUpdate
  extends Phobject {

  private $retry = false;
  private $state;
  private $markup;

  public function setRetry($retry) {
    $this->retry = $retry;
    return $this;
  }

  public function getRetry() {
    return $this->retry;
  }

  public function setState($state) {
    $this->state = $state;
    return $this;
  }

  public function getState() {
    return $this->state;
  }

  public function setMarkup($markup) {
    $this->markup = $markup;
    return $this;
  }

  public function getMarkup() {
    return $this->markup;
  }

  public function newContent() {
    return array(
      'retry' => $this->getRetry(),
      'state' => $this->getState(),
      'markup' => $this->getMarkup(),
    );
  }
}
