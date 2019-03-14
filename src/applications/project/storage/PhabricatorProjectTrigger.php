<?php

final class PhabricatorProjectTrigger
  extends PhabricatorProjectDAO
  implements
    PhabricatorApplicationTransactionInterface,
    PhabricatorPolicyInterface,
    PhabricatorDestructibleInterface {

  protected $name;
  protected $ruleset = array();
  protected $editPolicy;

  private $triggerRules;

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

  public function getTriggerRules() {
    if ($this->triggerRules === null) {

      // TODO: Temporary hard-coded rule specification.
      $rule_specifications = array(
        array(
          'type' => 'status',
          'value' => ManiphestTaskStatus::getDefaultClosedStatus(),
        ),
        // This is an intentionally unknown rule.
        array(
          'type' => 'quack',
          'value' => 'aaa',
        ),
        // This is an intentionally invalid rule.
        array(
          'type' => 'status',
          'value' => 'quack',
        ),
      );

      // NOTE: We're trying to preserve the database state in the rule
      // structure, even if it includes rule types we don't have implementations
      // for, or rules with invalid rule values.

      // If an administrator adds or removes extensions which add rules, or
      // an upgrade affects rule validity, existing rules may become invalid.
      // When they do, we still want the UI to reflect the ruleset state
      // accurately and "Edit" + "Save" shouldn't destroy data unless the
      // user explicitly modifies the ruleset.

      // When we run into rules which are structured correctly but which have
      // types we don't know about, we replace them with "Unknown Rules". If
      // we know about the type of a rule but the value doesn't validate, we
      // replace it with "Invalid Rules". These two rule types don't take any
      // actions when a card is dropped into the column, but they show the user
      // what's wrong with the ruleset and can be saved without causing any
      // collateral damage.

      $rule_map = PhabricatorProjectTriggerRule::getAllTriggerRules();

      // If the stored rule data isn't a list of rules (or we encounter other
      // fundamental structural problems, below), there isn't much we can do
      // to try to represent the state.
      if (!is_array($rule_specifications)) {
        throw new PhabricatorProjectTriggerCorruptionException(
          pht(
            'Trigger ("%s") has a corrupt ruleset: expected a list of '.
            'rule specifications, found "%s".',
            $this->getPHID(),
            phutil_describe_type($rule_specifications)));
      }

      $trigger_rules = array();
      foreach ($rule_specifications as $key => $rule) {
        if (!is_array($rule)) {
          throw new PhabricatorProjectTriggerCorruptionException(
            pht(
              'Trigger ("%s") has a corrupt ruleset: rule (with key "%s") '.
              'should be a rule specification, but is actually "%s".',
              $this->getPHID(),
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
              'Trigger ("%s") has a corrupt ruleset: rule (with key "%s") '.
              'is not a valid rule specification: %s',
              $this->getPHID(),
              $key,
              $ex->getMessage()));
        }

        $record = id(new PhabricatorProjectTriggerRuleRecord())
          ->setType(idx($rule, 'type'))
          ->setValue(idx($rule, 'value'));

        if (!isset($rule_map[$record->getType()])) {
          $rule = new PhabricatorProjectTriggerUnknownRule();
        } else {
          $rule = clone $rule_map[$record->getType()];
        }

        try {
          $rule->setRecord($record);
        } catch (Exception $ex) {
          $rule = id(new PhabricatorProjectTriggerInvalidRule())
            ->setRecord($record);
        }

        $trigger_rules[] = $rule;
      }

      $this->triggerRules = $trigger_rules;
    }

    return $this->triggerRules;
  }

  public function getRulesDescription() {
    $rules = $this->getTriggerRules();
    if (!$rules) {
      return pht('Does nothing.');
    }

    $things = array();

    $count = count($rules);
    $limit = 3;

    if ($count > $limit) {
      $show_rules = array_slice($rules, 0, ($limit - 1));
    } else {
      $show_rules = $rules;
    }

    foreach ($show_rules as $rule) {
      $things[] = $rule->getDescription();
    }

    if ($count > $limit) {
      $things[] = pht(
        '(Applies %s more actions.)',
        new PhutilNumber($count - $limit));
    }

    return implode("\n", $things);
  }

  public function newDropTransactions(
    PhabricatorUser $viewer,
    PhabricatorProjectColumn $column,
    $object) {

    $trigger_xactions = array();
    foreach ($this->getTriggerRules() as $rule) {
      $rule
        ->setViewer($viewer)
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

      $this->delete();

    $this->saveTransaction();
  }

}
