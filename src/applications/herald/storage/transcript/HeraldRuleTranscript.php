<?php

final class HeraldRuleTranscript extends Phobject {

  protected $ruleID;
  protected $result;
  protected $reason;

  protected $ruleName;
  protected $ruleOwner;

  public function setResult($result) {
    $this->result = $result;
    return $this;
  }

  public function getResult() {
    return $this->result;
  }

  public function setReason($reason) {
    $this->reason = $reason;
    return $this;
  }

  public function getReason() {
    return $this->reason;
  }

  public function setRuleID($rule_id) {
    $this->ruleID = $rule_id;
    return $this;
  }

  public function getRuleID() {
    return $this->ruleID;
  }

  public function setRuleName($rule_name) {
    $this->ruleName = $rule_name;
    return $this;
  }

  public function getRuleName() {
    return $this->ruleName;
  }

  public function setRuleOwner($rule_owner) {
    $this->ruleOwner = $rule_owner;
    return $this;
  }

  public function getRuleOwner() {
    return $this->ruleOwner;
  }
}
