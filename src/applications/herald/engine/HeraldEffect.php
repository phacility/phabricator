<?php

final class HeraldEffect {

  private $objectPHID;
  private $action;
  private $target;
  private $rule;
  private $reason;

  public function setObjectPHID($object_phid) {
    $this->objectPHID = $object_phid;
    return $this;
  }

  public function getObjectPHID() {
    return $this->objectPHID;
  }

  public function setAction($action) {
    $this->action = $action;
    return $this;
  }

  public function getAction() {
    return $this->action;
  }

  public function setTarget($target) {
    $this->target = $target;
    return $this;
  }

  public function getTarget() {
    return $this->target;
  }

  public function setRule(HeraldRule $rule) {
    $this->rule = $rule;
    return $this;
  }

  public function getRule() {
    return $this->rule;
  }

  public function setReason($reason) {
    $this->reason = $reason;
    return $this;
  }

  public function getReason() {
    return $this->reason;
  }

}
