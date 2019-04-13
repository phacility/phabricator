<?php

final class PhabricatorProjectTrigger
  extends PhabricatorProjectDAO
  implements
    PhabricatorApplicationTransactionInterface,
    PhabricatorPolicyInterface,
    PhabricatorIndexableInterface,
    PhabricatorDestructibleInterface {

  protected $name;
  protected $ruleset = array();
  protected $editPolicy;

  private $triggerRules;
  private $viewer;
  private $usage = self::ATTACHABLE;

  public static function initializeNewTrigger() {
    $default_edit = PhabricatorPolicies::POLICY_USER;

    return id(new self())
      ->setName('')
      ->setEditPolicy($default_edit);
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_SERIALIZATION => array(
        'ruleset' => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_COLUMN_SCHEMA => array(
        'name' => 'text255',
      ),
      self::CONFIG_KEY_SCHEMA => array(
      ),
    ) + parent::getConfiguration();
  }

  public function getPHIDType() {
    return PhabricatorProjectTriggerPHIDType::TYPECONST;
  }

  public function getViewer() {
    return $this->viewer;
  }

  public function setViewer(PhabricatorUser $user) {
    $this->viewer = $user;
    return $this;
  }

  public function getDisplayName() {
    $name = $this->getName();
    if (strlen($name)) {
      return $name;
    }

    return $this->getDefaultName();
  }

  public function getDefaultName() {
    return pht('Custom Trigger');
  }

  public function getURI() {
    return urisprintf(
      '/project/trigger/%d/',
      $this->getID());
  }

  public function getObjectName() {
    return pht('Trigger %d', $this->getID());
  }

  public function setRuleset(array $ruleset) {
    // Clear any cached trigger rules, since we're changing the ruleset
    // for the trigger.
    $this->triggerRules = null;

    parent::setRuleset($ruleset);
  }

  public function getTriggerRules($viewer = null) {
    if ($this->triggerRules === null) {
      if (!$viewer) {
        $viewer = $this->getViewer();
      }

      $trigger_rules = self::newTriggerRulesFromRuleSpecifications(
        $this->getRuleset(),
        $allow_invalid = true,
        $viewer);

      $this->triggerRules = $trigger_rules;
    }

    return $this->triggerRules;
  }

  public static function newTriggerRulesFromRuleSpecifications(
    array $list,
    $allow_invalid,
    PhabricatorUser $viewer) {

    // NOTE: With "$allow_invalid" set, we're trying to preserve the database
    // state in the rule structure, even if it includes rule types we don't
    // have implementations for, or rules with invalid rule values.

    // If an administrator adds or removes extensions which add rules, or
    // an upgrade affects rule validity, existing rules may become invalid.
    // When they do, we still want the UI to reflect the ruleset state
    // accurately and "Edit" + "Save" shouldn't destroy data unless the
    // user explicitly modifies the ruleset.

    // In this mode, when we run into rules which are structured correctly but
    // which have types we don't know about, we replace them with "Unknown
    // Rules". If we know about the type of a rule but the value doesn't
    // validate, we replace it with "Invalid Rules". These two rule types don't
    // take any actions when a card is dropped into the column, but they show
    // the user what's wrong with the ruleset and can be saved without causing
    // any collateral damage.

    $rule_map = PhabricatorProjectTriggerRule::getAllTriggerRules();

    // If the stored rule data isn't a list of rules (or we encounter other
    // fundamental structural problems, below), there isn't much we can do
    // to try to represent the state.
    if (!is_array($list)) {
      throw new PhabricatorProjectTriggerCorruptionException(
        pht(
          'Trigger ruleset is corrupt: expected a list of rule '.
          'specifications, found "%s".',
          phutil_describe_type($list)));
    }

    $trigger_rules = array();
    foreach ($list as $key => $rule) {
      if (!is_array($rule)) {
        throw new PhabricatorProjectTriggerCorruptionException(
          pht(
            'Trigger ruleset is corrupt: rule (at index "%s") should be a '.
            'rule specification, but is actually "%s".',
            $key,
            phutil_describe_type($rule)));
      }

      try {
        PhutilTypeSpec::checkMap(
          $rule,
          array(
            'type' => 'string',
            'value' => 'wild',
          ));
      } catch (PhutilTypeCheckException $ex) {
        throw new PhabricatorProjectTriggerCorruptionException(
          pht(
            'Trigger ruleset is corrupt: rule (at index "%s") is not a '.
            'valid rule specification: %s',
            $key,
            $ex->getMessage()));
      }

      $record = id(new PhabricatorProjectTriggerRuleRecord())
        ->setType(idx($rule, 'type'))
        ->setValue(idx($rule, 'value'));

      if (!isset($rule_map[$record->getType()])) {
        if (!$allow_invalid) {
          throw new PhabricatorProjectTriggerCorruptionException(
            pht(
              'Trigger ruleset is corrupt: rule type "%s" is unknown.',
              $record->getType()));
        }

        $rule = new PhabricatorProjectTriggerUnknownRule();
      } else {
        $rule = clone $rule_map[$record->getType()];
      }

      try {
        $rule->setRecord($record);
      } catch (Exception $ex) {
        if (!$allow_invalid) {
          throw new PhabricatorProjectTriggerCorruptionException(
            pht(
              'Trigger ruleset is corrupt, rule (of type "%s") does not '.
              'validate: %s',
              $record->getType(),
              $ex->getMessage()));
        }

        $rule = id(new PhabricatorProjectTriggerInvalidRule())
          ->setRecord($record)
          ->setException($ex);
      }
      $rule->setViewer($viewer);

      $trigger_rules[] = $rule;
    }

    return $trigger_rules;
  }


  public function getDropEffects() {
    $effects = array();

    $rules = $this->getTriggerRules();
    foreach ($rules as $rule) {
      foreach ($rule->getDropEffects() as $effect) {
        $effects[] = $effect;
      }
    }

    return $effects;
  }

  public function newDropTransactions(
    PhabricatorUser $viewer,
    PhabricatorProjectColumn $column,
    $object) {

    $trigger_xactions = array();
    foreach ($this->getTriggerRules($viewer) as $rule) {
      $rule
        ->setTrigger($this)
        ->setColumn($column)
        ->setObject($object);

      $xactions = $rule->getDropTransactions(
        $object,
        $rule->getRecord()->getValue());

      if (!is_array($xactions)) {
        throw new Exception(
          pht(
            'Expected trigger rule (of class "%s") to return a list of '.
            'transactions from "newDropTransactions()", but got "%s".',
            get_class($rule),
            phutil_describe_type($xactions)));
      }

      $expect_type = get_class($object->getApplicationTransactionTemplate());
      assert_instances_of($xactions, $expect_type);

      foreach ($xactions as $xaction) {
        $trigger_xactions[] = $xaction;
      }
    }

    return $trigger_xactions;
  }

  public function getPreviewEffect() {
    $header = pht('Trigger: %s', $this->getDisplayName());

    return id(new PhabricatorProjectDropEffect())
      ->setIcon('fa-cogs')
      ->setColor('blue')
      ->setIsHeader(true)
      ->setContent($header);
  }

  public function getSoundEffects() {
    $sounds = array();

    foreach ($this->getTriggerRules() as $rule) {
      foreach ($rule->getSoundEffects() as $effect) {
        $sounds[] = $effect;
      }
    }

    return $sounds;
  }

  public function getUsage() {
    return $this->assertAttached($this->usage);
  }

  public function attachUsage(PhabricatorProjectTriggerUsage $usage) {
    $this->usage = $usage;
    return $this;
  }


/* -(  PhabricatorApplicationTransactionInterface  )------------------------- */


  public function getApplicationTransactionEditor() {
    return new PhabricatorProjectTriggerEditor();
  }

  public function getApplicationTransactionTemplate() {
    return new PhabricatorProjectTriggerTransaction();
  }


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
      PhabricatorPolicyCapability::CAN_EDIT,
    );
  }

  public function getPolicy($capability) {
    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_VIEW:
        return PhabricatorPolicies::getMostOpenPolicy();
      case PhabricatorPolicyCapability::CAN_EDIT:
        return $this->getEditPolicy();
    }
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return false;
  }


/* -(  PhabricatorDestructibleInterface  )----------------------------------- */


  public function destroyObjectPermanently(
    PhabricatorDestructionEngine $engine) {

    $this->openTransaction();
      $conn = $this->establishConnection('w');

      // Remove the reference to this trigger from any columns which use it.
      queryfx(
        $conn,
        'UPDATE %R SET triggerPHID = null WHERE triggerPHID = %s',
        new PhabricatorProjectColumn(),
        $this->getPHID());

      // Remove the usage index row for this trigger, if one exists.
      queryfx(
        $conn,
        'DELETE FROM %R WHERE triggerPHID = %s',
        new PhabricatorProjectTriggerUsage(),
        $this->getPHID());

      $this->delete();

    $this->saveTransaction();
  }

}
