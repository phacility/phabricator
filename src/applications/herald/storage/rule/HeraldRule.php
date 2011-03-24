<?php

/*
 * Copyright 2011 Facebook, Inc.
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

class HeraldRule extends HeraldDAO {

  protected $name;
  protected $authorPHID;

  protected $contentType;
  protected $mustMatchAll;

  protected $configVersion = 7;

  public static function loadAllByContentTypeWithFullData($content_type) {
    $rules = id(new HeraldRule())->loadAllWhere(
      'contentType = %s',
      $content_type);
    $rule_ids = mpull($rules, 'getID');

    $conditions = id(new HeraldCondition())->loadAllWhere(
      'ruleID in (%Ld)',
      $rule_ids);

    $actions = id(new HeraldAction())->loadAllWhere(
      'ruleID in (%Ld)',
      $rule_ids);

    $conditions = mgroup($conditions, 'getRuleID');
    $actions = mgroup($actions, 'getRuleID');

    foreach ($rules as $rule) {
      $rule->attachConditions(idx($conditions, $rule->getID(), array()));
      $rule->attachActions(idx($actions, $rule->getID(), array()));
    }

    return $rules;
  }

  public function loadConditions() {
    if (!$this->getID()) {
      return array();
    }
    return id(new HeraldCondition())->loadAllWhere(
      'ruleID = %d',
      $this->getID());
  }

  public function attachConditions(array $conditions) {
    $this->conditions = $conditions;
    return $this;
  }

  public function getConditions() {
    // TODO: validate conditions have been attached.
    return $this->conditions;
  }

  public function loadActions() {
    if (!$this->getID()) {
      return array();
    }
    return id(new HeraldAction())->loadAllWhere(
      'ruleID = %d',
      $this->getID());
  }

  public function attachActions(array $actions) {
    // TODO: validate actions have been attached.
    $this->actions = $actions;
    return $this;
  }

  public function getActions() {
    return $this->actions;
  }

  public function saveConditions(array $conditions) {
    return $this->saveChildren(
      id(new HeraldCondition())->getTableName(),
      $conditions);
  }

  public function saveActions(array $actions) {
    return $this->saveChildren(
      id(new HeraldAction())->getTableName(),
      $actions);
  }

  protected function saveChildren($table_name, array $children) {
    if (!$this->getID()) {
      throw new Exception("Save rule before saving children.");
    }

    foreach ($children as $child) {
      $child->setRuleID($this->getID());
    }

// TODO:
//    $this->openTransaction();
      queryfx(
        $this->establishConnection('w'),
        'DELETE FROM %T WHERE ruleID = %d',
        $table_name,
        $this->getID());
      foreach ($children as $child) {
        $child->save();
      }
//    $this->saveTransaction();
  }

  public function delete() {

// TODO:
//    $this->openTransaction();
      queryfx(
        $this->establishConnection('w'),
        'DELETE FROM %T WHERE ruleID = %d',
        id(new HeraldCondition())->getTableName(),
        $this->getID());
      queryfx(
        $this->establishConnection('w'),
        'DELETE FROM %T WHERE ruleID = %d',
        id(new HeraldAction())->getTableName(),
        $this->getID());
      parent::delete();
//    $this->saveTransaction();
  }

}
