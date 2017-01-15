<?php

final class PhabricatorGuidanceMessage
  extends Phobject {

  private $key;
  private $message;
  private $severity = self::SEVERITY_NOTICE;
  private $priority = 1000;

  const SEVERITY_NOTICE = 'notice';
  const SEVERITY_WARNING = 'warning';

  public function setSeverity($severity) {
    $this->severity = $severity;
    return $this;
  }

  public function getSeverity() {
    return $this->severity;
  }

  public function setKey($key) {
    $this->key = $key;
    return $this;
  }

  public function getKey() {
    return $this->key;
  }

  public function setMessage($message) {
    $this->message = $message;
    return $this;
  }

  public function getMessage() {
    return $this->message;
  }

  public function getSortVector() {
    return id(new PhutilSortVector())
      ->addInt($this->getPriority());
  }

  public function setPriority($priority) {
    $this->priority = $priority;
    return $this;
  }

  public function getPriority() {
    return $this->priority;
  }

  public function getSeverityStrength() {
    $map = array(
      self::SEVERITY_NOTICE => 1,
      self::SEVERITY_WARNING => 2,
    );

    return idx($map, $this->getSeverity(), 0);
  }


}
