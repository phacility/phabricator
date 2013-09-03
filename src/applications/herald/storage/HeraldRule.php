<?php

final class HeraldRule extends HeraldDAO
  implements PhabricatorPolicyInterface {

  const TABLE_RULE_APPLIED = 'herald_ruleapplied';

  protected $name;
  protected $authorPHID;

  protected $contentType;
  protected $mustMatchAll;
  protected $repetitionPolicy;
  protected $ruleType;

  protected $configVersion = 9;

  private $ruleApplied = self::ATTACHABLE; // phids for which this rule has been applied
  private $validAuthor = self::ATTACHABLE;
  private $conditions;
  private $actions;

  public function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(HeraldPHIDTypeRule::TYPECONST);
  }

  public function getRuleApplied($phid) {
    return $this->assertAttachedKey($this->ruleApplied, $phid);
  }

  public function setRuleApplied($phid, $applied) {
    if ($this->ruleApplied === self::ATTACHABLE) {
      $this->ruleApplied = array();
    }
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

  public function hasValidAuthor() {
    return $this->assertAttached($this->validAuthor);
  }

  public function attachValidAuthor($valid) {
    $this->validAuthor = $valid;
    return $this;
  }

  public function isGlobalRule() {
    return ($this->getRuleType() === HeraldRuleTypeConfig::RULE_TYPE_GLOBAL);
  }

  public function isPersonalRule() {
    return ($this->getRuleType() === HeraldRuleTypeConfig::RULE_TYPE_PERSONAL);
  }


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
      PhabricatorPolicyCapability::CAN_EDIT,
    );
  }

  public function getPolicy($capability) {
    if ($this->isGlobalRule()) {
      return PhabricatorPolicies::POLICY_USER;
    } else {
      return PhabricatorPolicies::POLICY_NOONE;
    }
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    if ($this->isPersonalRule()) {
      return ($viewer->getPHID() == $this->getAuthorPHID());
    } else {
      return false;
    }
  }

}
