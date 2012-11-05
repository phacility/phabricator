<?php

final class HeraldEffect {

  protected $objectPHID;
  protected $action;
  protected $target;

  protected $ruleID;
  protected $effector;

  protected $reason;

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

  public function setRuleID($rule_id) {
    $this->ruleID = $rule_id;
    return $this;
  }

  public function getRuleID() {
    return $this->ruleID;
  }

  public function setEffector($effector) {
    $this->effector = $effector;
    return $this;
  }

  public function getEffector() {
    return $this->effector;
  }

  public function setReason($reason) {
    $this->reason = $reason;
    return $this;
  }

  public function getReason() {
    return $this->reason;
  }

}

