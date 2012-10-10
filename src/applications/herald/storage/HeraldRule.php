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

final class HeraldRule extends HeraldDAO {

  const TABLE_RULE_APPLIED = 'herald_ruleapplied';

  protected $name;
  protected $authorPHID;

  protected $contentType;
  protected $mustMatchAll;
  protected $repetitionPolicy;
  protected $ruleType;

  protected $configVersion = 9;

  private $ruleApplied = array(); // phids for which this rule has been applied
  private $invalidOwner = false;
  private $conditions;
  private $actions;

  public static function loadAllByContentTypeWithFullData(
    $content_type,
    $object_phid) {

    $rules = id(new HeraldRule())->loadAllWhere(
      'contentType = %s',
      $content_type);

    if (!$rules) {
      return array();
    }

    self::flagDisabledUserRules($rules);

    $rule_ids = mpull($rules, 'getID');

    $conditions = id(new HeraldCondition())->loadAllWhere(
      'ruleID in (%Ld)',
      $rule_ids);

    $actions = id(new HeraldAction())->loadAllWhere(
      'ruleID in (%Ld)',
      $rule_ids);

    $applied = queryfx_all(
      id(new HeraldRule())->establishConnection('r'),
      'SELECT * FROM %T WHERE phid = %s',
      self::TABLE_RULE_APPLIED,
      $object_phid);
    $applied = ipull($applied, null, 'ruleID');

    $conditions = mgroup($conditions, 'getRuleID');
    $actions = mgroup($actions, 'getRuleID');
    $applied = igroup($applied, 'ruleID');

    foreach ($rules as $rule) {
      $rule->setRuleApplied($object_phid, isset($applied[$rule->getID()]));

      $rule->attachConditions(idx($conditions, $rule->getID(), array()));
      $rule->attachActions(idx($actions, $rule->getID(), array()));
    }

    return $rules;
  }

  private static function flagDisabledUserRules(array $rules) {
    assert_instances_of($rules, 'HeraldRule');

    $users = array();
    foreach ($rules as $rule) {
      if ($rule->getRuleType() != HeraldRuleTypeConfig::RULE_TYPE_PERSONAL) {
        continue;
      }
      $users[$rule->getAuthorPHID()] = true;
    }

    $handles = id(new PhabricatorObjectHandleData(array_keys($users)))
      ->loadHandles();

    foreach ($rules as $key => $rule) {
      if ($rule->getRuleType() != HeraldRuleTypeConfig::RULE_TYPE_PERSONAL) {
        continue;
      }
      $handle = $handles[$rule->getAuthorPHID()];
      if (!$handle->isComplete() || $handle->isDisabled()) {
        $rule->invalidOwner = true;
      }
    }
  }

  public function getRuleApplied($phid) {
    if (idx($this->ruleApplied, $phid) === null) {
      throw new Exception("Call setRuleApplied() before getRuleApplied()!");
    }
    return $this->ruleApplied[$phid];
  }

  public function setRuleApplied($phid, $applied) {
    $this->ruleApplied[$phid] = $applied;
    return $this;
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
    assert_instances_of($conditions, 'HeraldCondition');
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
    assert_instances_of($actions, 'HeraldAction');
    $this->actions = $actions;
    return $this;
  }

  public function getActions() {
    return $this->actions;
  }

  public function loadEdits() {
    if (!$this->getID()) {
      return array();
    }
    $edits = id(new HeraldRuleEdit())->loadAllWhere(
      'ruleID = %d ORDER BY dateCreated DESC',
      $this->getID());

    return $edits;
  }

  public function logEdit($editor_phid, $action) {
    id(new HeraldRuleEdit())
      ->setRuleID($this->getID())
      ->setRuleName($this->getName())
      ->setEditorPHID($editor_phid)
      ->setAction($action)
      ->save();
  }

  public function saveConditions(array $conditions) {
    assert_instances_of($conditions, 'HeraldCondition');
    return $this->saveChildren(
      id(new HeraldCondition())->getTableName(),
      $conditions);
  }

  public function saveActions(array $actions) {
    assert_instances_of($actions, 'HeraldAction');
    return $this->saveChildren(
      id(new HeraldAction())->getTableName(),
      $actions);
  }

  protected function saveChildren($table_name, array $children) {
    assert_instances_of($children, 'HeraldDAO');

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

  public function hasInvalidOwner() {
    return $this->invalidOwner;
  }

}
