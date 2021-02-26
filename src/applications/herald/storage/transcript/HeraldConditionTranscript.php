<?php

final class HeraldConditionTranscript extends Phobject {

  protected $ruleID;
  protected $conditionID;
  protected $fieldName;
  protected $condition;
  protected $testValue;
  protected $resultMap;

  // See T13586. Older versions of this record stored a boolean true, boolean
  // false, or the string "forbidden" in the "$result" field. They stored a
  // human-readable English-language message or a state code in the "$note"
  // field.

  // The modern record does not use either field.

  protected $result;
  protected $note;

  public function setRuleID($rule_id) {
    $this->ruleID = $rule_id;
    return $this;
  }

  public function getRuleID() {
    return $this->ruleID;
  }

  public function setConditionID($condition_id) {
    $this->conditionID = $condition_id;
    return $this;
  }

  public function getConditionID() {
    return $this->conditionID;
  }

  public function setFieldName($field_name) {
    $this->fieldName = $field_name;
    return $this;
  }

  public function getFieldName() {
    return $this->fieldName;
  }

  public function setCondition($condition) {
    $this->condition = $condition;
    return $this;
  }

  public function getCondition() {
    return $this->condition;
  }

  public function setTestValue($test_value) {
    $this->testValue = $test_value;
    return $this;
  }

  public function getTestValue() {
    return $this->testValue;
  }

  public function setResult(HeraldConditionResult $result) {
    $this->resultMap = $result->newResultMap();
    return $this;
  }

  public function getResult() {
    $map = $this->resultMap;

    if (is_array($map)) {
      $result = HeraldConditionResult::newFromResultMap($map);
    } else {
      $legacy_result = $this->result;

      $result_data = array();

      if ($legacy_result === 'forbidden') {
        $result_code = HeraldConditionResult::RESULT_OBJECT_STATE;
        $result_data = array(
          'reason' => $this->note,
        );
      } else if ($legacy_result === true) {
        $result_code = HeraldConditionResult::RESULT_MATCHED;
      } else if ($legacy_result === false) {
        $result_code = HeraldConditionResult::RESULT_FAILED;
      } else {
        $result_code = HeraldConditionResult::RESULT_UNKNOWN;
      }

      $result = HeraldConditionResult::newFromResultCode($result_code)
        ->setResultData($result_data);
    }

    return $result;
  }

}
