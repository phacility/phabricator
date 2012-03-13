<?php

/*
 * Copyright 2012 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

final class HeraldConditionTranscript {

  protected $ruleID;
  protected $conditionID;
  protected $fieldName;
  protected $condition;
  protected $testValue;
  protected $note;
  protected $result;

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

  public function setNote($note) {
    $this->note = $note;
    return $this;
  }

  public function getNote() {
    return $this->note;
  }

  public function setResult($result) {
    $this->result = $result;
    return $this;
  }

  public function getResult() {
    return $this->result;
  }
}
