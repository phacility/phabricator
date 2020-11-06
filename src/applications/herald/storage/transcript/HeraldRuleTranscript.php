<?php

final class HeraldRuleTranscript extends Phobject {

  protected $ruleID;
  protected $ruleResultMap;
  protected $ruleName;
  protected $ruleOwner;

  // See T13586. This no longer has readers, but was written by older versions
  // of Herald. It contained a human readable English-language description of
  // the outcome of rule evaluation and was superseded by "HeraldRuleResult".
  protected $reason;

  // See T13586. Older transcripts store a boolean "true", a boolean "false",
  // or the string "forbidden" here.
  protected $result;

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

  public function setRuleResult(HeraldRuleResult $result) {
    $this->ruleResultMap = $result->newResultMap();
    return $this;
  }

  public function getRuleResult() {
    $map = $this->ruleResultMap;

    if (is_array($map)) {
      $result = HeraldRuleResult::newFromResultMap($map);
    } else {
      $legacy_result = $this->result;

      $result_data = array();

      if ($legacy_result === 'forbidden') {
        $result_code = HeraldRuleResult::RESULT_OBJECT_STATE;
        $result_data = array(
          'reason' => $this->reason,
        );
      } else if ($legacy_result === true) {
        $result_code = HeraldRuleResult::RESULT_ANY_MATCHED;
      } else if ($legacy_result === false) {
        $result_code = HeraldRuleResult::RESULT_ANY_FAILED;
      } else {
        $result_code = HeraldRuleResult::RESULT_UNKNOWN;
      }

      $result = HeraldRuleResult::newFromResultCode($result_code)
        ->setResultData($result_data);
    }

    return $result;
  }

}
