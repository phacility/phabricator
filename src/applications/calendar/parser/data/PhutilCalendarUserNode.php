<?php

final class PhutilCalendarUserNode extends PhutilCalendarNode {

  private $name;
  private $uri;
  private $status;

  const STATUS_INVITED = 'invited';
  const STATUS_ACCEPTED = 'accepted';
  const STATUS_DECLINED = 'declined';

  public function setName($name) {
    $this->name = $name;
    return $this;
  }

  public function getName() {
    return $this->name;
  }

  public function setURI($uri) {
    $this->uri = $uri;
    return $this;
  }

  public function getURI() {
    return $this->uri;
  }

  public function setStatus($status) {
    $this->status = $status;
    return $this;
  }

  public function getStatus() {
    return $this->status;
  }

}
