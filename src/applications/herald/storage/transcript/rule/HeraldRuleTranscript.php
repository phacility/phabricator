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

final class HeraldRuleTranscript {

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
