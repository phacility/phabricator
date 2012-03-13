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
