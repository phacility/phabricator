<?php

final class HeraldRule extends HeraldDAO
  implements
    PhabricatorApplicationTransactionInterface,
    PhabricatorFlaggableInterface,
    PhabricatorPolicyInterface,
    PhabricatorDestructibleInterface {

  const TABLE_RULE_APPLIED = 'herald_ruleapplied';

  protected $name;
  protected $authorPHID;

  protected $contentType;
  protected $mustMatchAll;
  protected $repetitionPolicy;
  protected $ruleType;
  protected $isDisabled = 0;
  protected $triggerObjectPHID;

  protected $configVersion = 38;

  // PHIDs for which this rule has been applied
  private $ruleApplied = self::ATTACHABLE;
  private $validAuthor = self::ATTACHABLE;
  private $author = self::ATTACHABLE;
  private $conditions;
  private $actions;
  private $triggerObject = self::ATTACHABLE;

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_COLUMN_SCHEMA => array(
        'name' => 'text255',
        'contentType' => 'text255',
        'mustMatchAll' => 'bool',
        'configVersion' => 'uint32',
        'ruleType' => 'text32',
        'isDisabled' => 'uint32',
        'triggerObjectPHID' => 'phid?',

        // T6203/NULLABILITY
        // This should not be nullable.
        'repetitionPolicy' => 'uint32?',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_author' => array(
          'columns' => array('authorPHID'),
        ),
        'key_ruletype' => array(
          'columns' => array('ruleType'),
        ),
        'key_trigger' => array(
          'columns' => array('triggerObjectPHID'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(HeraldRulePHIDType::TYPECONST);
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
      throw new PhutilInvalidStateException('save');
    }

    foreach ($children as $child) {
      $child->setRuleID($this->getID());
    }

    $this->openTransaction();
      queryfx(
        $this->establishConnection('w'),
        'DELETE FROM %T WHERE ruleID = %d',
        $table_name,
        $this->getID());
      foreach ($children as $child) {
        $child->save();
      }
    $this->saveTransaction();
  }

  public function delete() {
    $this->openTransaction();
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
      $result = parent::delete();
    $this->saveTransaction();

    return $result;
  }

  public function hasValidAuthor() {
    return $this->assertAttached($this->validAuthor);
  }

  public function attachValidAuthor($valid) {
    $this->validAuthor = $valid;
    return $this;
  }

  public function getAuthor() {
    return $this->assertAttached($this->author);
  }

  public function attachAuthor(PhabricatorUser $user) {
    $this->author = $user;
    return $this;
  }

  public function isGlobalRule() {
    return ($this->getRuleType() === HeraldRuleTypeConfig::RULE_TYPE_GLOBAL);
  }

  public function isPersonalRule() {
    return ($this->getRuleType() === HeraldRuleTypeConfig::RULE_TYPE_PERSONAL);
  }

  public function isObjectRule() {
    return ($this->getRuleType() == HeraldRuleTypeConfig::RULE_TYPE_OBJECT);
  }

  public function attachTriggerObject($trigger_object) {
    $this->triggerObject = $trigger_object;
    return $this;
  }

  public function getTriggerObject() {
    return $this->assertAttached($this->triggerObject);
  }

  /**
   * Get a sortable key for rule execution order.
   *
   * Rules execute in a well-defined order: personal rules first, then object
   * rules, then global rules. Within each rule type, rules execute from lowest
   * ID to highest ID.
   *
   * This ordering allows more powerful rules (like global rules) to override
   * weaker rules (like personal rules) when multiple rules exist which try to
   * affect the same field. Executing from low IDs to high IDs makes
   * interactions easier to understand when adding new rules, because the newest
   * rules always happen last.
   *
   * @return string A sortable key for this rule.
   */
  public function getRuleExecutionOrderSortKey() {

    $rule_type = $this->getRuleType();

    switch ($rule_type) {
      case HeraldRuleTypeConfig::RULE_TYPE_PERSONAL:
        $type_order = 1;
        break;
      case HeraldRuleTypeConfig::RULE_TYPE_OBJECT:
        $type_order = 2;
        break;
      case HeraldRuleTypeConfig::RULE_TYPE_GLOBAL:
        $type_order = 3;
        break;
      default:
        throw new Exception(pht('Unknown rule type "%s"!', $rule_type));
    }

    return sprintf('~%d%010d', $type_order, $this->getID());
  }

  public function getMonogram() {
    return 'H'.$this->getID();
  }


/* -(  PhabricatorApplicationTransactionInterface  )------------------------- */


  public function getApplicationTransactionEditor() {
    return new HeraldRuleEditor();
  }

  public function getApplicationTransactionObject() {
    return $this;
  }

  public function getApplicationTransactionTemplate() {
    return new HeraldRuleTransaction();
  }

  public function willRenderTimeline(
    PhabricatorApplicationTransactionView $timeline,
    AphrontRequest $request) {

    return $timeline;
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
      switch ($capability) {
        case PhabricatorPolicyCapability::CAN_VIEW:
          return PhabricatorPolicies::POLICY_USER;
        case PhabricatorPolicyCapability::CAN_EDIT:
          $app = 'PhabricatorHeraldApplication';
          $herald = PhabricatorApplication::getByClass($app);
          $global = HeraldManageGlobalRulesCapability::CAPABILITY;
          return $herald->getPolicy($global);
      }
    } else if ($this->isObjectRule()) {
      return $this->getTriggerObject()->getPolicy($capability);
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

  public function describeAutomaticCapability($capability) {
    if ($this->isPersonalRule()) {
      return pht("A personal rule's owner can always view and edit it.");
    } else if ($this->isObjectRule()) {
      return pht('Object rules inherit the policies of their objects.');
    }

    return null;
  }


/* -(  PhabricatorDestructibleInterface  )----------------------------------- */

  public function destroyObjectPermanently(
    PhabricatorDestructionEngine $engine) {

    $this->openTransaction();
    $this->delete();
    $this->saveTransaction();
  }

}
