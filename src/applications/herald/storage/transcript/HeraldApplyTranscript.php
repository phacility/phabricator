<?php

final class HeraldApplyTranscript extends Phobject {

  private $action;
  private $target;
  private $ruleID;
  private $reason;
  private $applied;
  private $appliedReason;

  public function __construct(
    HeraldEffect $effect,
    $applied,
    $reason = null) {

    $this->setAction($effect->getAction());
    $this->setTarget($effect->getTarget());
    if ($effect->getRule()) {
      $this->setRuleID($effect->getRule()->getID());
    }
    $this->setReason($effect->getReason());
    $this->setApplied($applied);
    $this->setAppliedReason($reason);
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

  public function setReason($reason) {
    $this->reason = $reason;
    return $this;
  }

  public function getReason() {
    return $this->reason;
  }

  public function setApplied($applied) {
    $this->applied = $applied;
    return $this;
  }

  public function getApplied() {
    return $this->applied;
  }

  public function setAppliedReason($applied_reason) {
    $this->appliedReason = $applied_reason;
    return $this;
  }

  public function getAppliedReason() {
    return $this->appliedReason;
  }

}
