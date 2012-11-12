<?php

final class HeraldApplyTranscript extends HeraldDAO {

  protected $action;
  protected $target;

  protected $ruleID;
  protected $effector;

  protected $reason;

  protected $applied;
  protected $appliedReason;

  public function __construct(
    HeraldEffect $effect,
    $applied,
    $reason = null) {

    $this->setAction($effect->getAction());
    $this->setTarget($effect->getTarget());
    $this->setRuleID($effect->getRuleID());
    $this->setEffector($effect->getEffector());
    $this->setReason($effect->getReason());
    $this->setApplied($applied);
    $this->setAppliedReason($reason);

  }

  public function getAction() {
    return $this->action;
  }

  public function getTarget() {
    return $this->target;
  }

  public function getRuleID() {
    return $this->ruleID;
  }

  public function getEffector() {
    return $this->effector;
  }

  public function getReason() {
    return $this->reason;
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
