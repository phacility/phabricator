<?php

final class HeraldEngine extends Phobject {

  protected $rules = array();
  protected $results = array();
  protected $stack = array();
  protected $activeRule;
  protected $transcript;

  protected $fieldCache = array();
  protected $object;
  private $dryRun;

  public function setDryRun($dry_run) {
    $this->dryRun = $dry_run;
    return $this;
  }

  public function getDryRun() {
    return $this->dryRun;
  }

  public function getRule($phid) {
    return idx($this->rules, $phid);
  }

  public function loadRulesForAdapter(HeraldAdapter $adapter) {
    return id(new HeraldRuleQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withDisabled(false)
      ->withContentTypes(array($adapter->getAdapterContentType()))
      ->needConditionsAndActions(true)
      ->needAppliedToPHIDs(array($adapter->getPHID()))
      ->needValidateAuthors(true)
      ->execute();
  }

  public static function loadAndApplyRules(HeraldAdapter $adapter) {
    $engine = new HeraldEngine();

    $rules = $engine->loadRulesForAdapter($adapter);
    $effects = $engine->applyRules($rules, $adapter);
    $engine->applyEffects($effects, $adapter, $rules);

    return $engine->getTranscript();
  }

  public function applyRules(array $rules, HeraldAdapter $object) {
    assert_instances_of($rules, 'HeraldRule');
    $t_start = microtime(true);

    // Rules execute in a well-defined order: sort them into execution order.
    $rules = msort($rules, 'getRuleExecutionOrderSortKey');
    $rules = mpull($rules, null, 'getPHID');

    $this->transcript = new HeraldTranscript();
    $this->transcript->setObjectPHID((string)$object->getPHID());
    $this->fieldCache = array();
    $this->results = array();
    $this->rules   = $rules;
    $this->object  = $object;

    $effects = array();
    foreach ($rules as $phid => $rule) {
      $this->stack = array();

      $policy_first = HeraldRepetitionPolicyConfig::FIRST;
      $policy_first_int = HeraldRepetitionPolicyConfig::toInt($policy_first);
      $is_first_only = ($rule->getRepetitionPolicy() == $policy_first_int);

      try {
        if (!$this->getDryRun() &&
            $is_first_only &&
            $rule->getRuleApplied($object->getPHID())) {
          // This is not a dry run, and this rule is only supposed to be
          // applied a single time, and it's already been applied...
          // That means automatic failure.
          $xscript = id(new HeraldRuleTranscript())
            ->setRuleID($rule->getID())
            ->setResult(false)
            ->setRuleName($rule->getName())
            ->setRuleOwner($rule->getAuthorPHID())
            ->setReason(
              pht(
                'This rule is only supposed to be repeated a single time, '.
                'and it has already been applied.'));
          $this->transcript->addRuleTranscript($xscript);
          $rule_matches = false;
        } else {
          $rule_matches = $this->doesRuleMatch($rule, $object);
        }
      } catch (HeraldRecursiveConditionsException $ex) {
        $names = array();
        foreach ($this->stack as $rule_id => $ignored) {
          $names[] = '"'.$rules[$rule_id]->getName().'"';
        }
        $names = implode(', ', $names);
        foreach ($this->stack as $rule_id => $ignored) {
          $xscript = new HeraldRuleTranscript();
          $xscript->setRuleID($rule_id);
          $xscript->setResult(false);
          $xscript->setReason(
            pht(
              "Rules %s are recursively dependent upon one another! ".
              "Don't do this! You have formed an unresolvable cycle in the ".
              "dependency graph!",
              $names));
          $xscript->setRuleName($rules[$rule_id]->getName());
          $xscript->setRuleOwner($rules[$rule_id]->getAuthorPHID());
          $this->transcript->addRuleTranscript($xscript);
        }
        $rule_matches = false;
      }
      $this->results[$phid] = $rule_matches;

      if ($rule_matches) {
        foreach ($this->getRuleEffects($rule, $object) as $effect) {
          $effects[] = $effect;
        }
      }
    }

    $object_transcript = new HeraldObjectTranscript();
    $object_transcript->setPHID($object->getPHID());
    $object_transcript->setName($object->getHeraldName());
    $object_transcript->setType($object->getAdapterContentType());
    $object_transcript->setFields($this->fieldCache);

    $this->transcript->setObjectTranscript($object_transcript);

    $t_end = microtime(true);

    $this->transcript->setDuration($t_end - $t_start);

    return $effects;
  }

  public function applyEffects(
    array $effects,
    HeraldAdapter $adapter,
    array $rules) {
    assert_instances_of($effects, 'HeraldEffect');
    assert_instances_of($rules, 'HeraldRule');

    $this->transcript->setDryRun((int)$this->getDryRun());

    if ($this->getDryRun()) {
      $xscripts = array();
      foreach ($effects as $effect) {
        $xscripts[] = new HeraldApplyTranscript(
          $effect,
          false,
          pht('This was a dry run, so no actions were actually taken.'));
      }
    } else {
      $xscripts = $adapter->applyHeraldEffects($effects);
    }

    assert_instances_of($xscripts, 'HeraldApplyTranscript');
    foreach ($xscripts as $apply_xscript) {
      $this->transcript->addApplyTranscript($apply_xscript);
    }

    // For dry runs, don't mark the rule as having applied to the object.
    if ($this->getDryRun()) {
      return;
    }

    $rules = mpull($rules, null, 'getID');
    $applied_ids = array();
    $first_policy = HeraldRepetitionPolicyConfig::toInt(
      HeraldRepetitionPolicyConfig::FIRST);

    // Mark all the rules that have had their effects applied as having been
    // executed for the current object.
    $rule_ids = mpull($xscripts, 'getRuleID');

    foreach ($rule_ids as $rule_id) {
      if (!$rule_id) {
        // Some apply transcripts are purely informational and not associated
        // with a rule, e.g. carryover emails from earlier revisions.
        continue;
      }

      $rule = idx($rules, $rule_id);
      if (!$rule) {
        continue;
      }

      if ($rule->getRepetitionPolicy() == $first_policy) {
        $applied_ids[] = $rule_id;
      }
    }

    if ($applied_ids) {
      $conn_w = id(new HeraldRule())->establishConnection('w');
      $sql = array();
      foreach ($applied_ids as $id) {
        $sql[] = qsprintf(
          $conn_w,
          '(%s, %d)',
          $adapter->getPHID(),
          $id);
      }
      queryfx(
        $conn_w,
        'INSERT IGNORE INTO %T (phid, ruleID) VALUES %Q',
        HeraldRule::TABLE_RULE_APPLIED,
        implode(', ', $sql));
    }
  }

  public function getTranscript() {
    $this->transcript->save();
    return $this->transcript;
  }

  public function doesRuleMatch(
    HeraldRule $rule,
    HeraldAdapter $object) {

    $phid = $rule->getPHID();

    if (isset($this->results[$phid])) {
      // If we've already evaluated this rule because another rule depends
      // on it, we don't need to reevaluate it.
      return $this->results[$phid];
    }

    if (isset($this->stack[$phid])) {
      // We've recursed, fail all of the rules on the stack. This happens when
      // there's a dependency cycle with "Rule conditions match for rule ..."
      // conditions.
      foreach ($this->stack as $rule_phid => $ignored) {
        $this->results[$rule_phid] = false;
      }
      throw new HeraldRecursiveConditionsException();
    }

    $this->stack[$phid] = true;

    $all = $rule->getMustMatchAll();

    $conditions = $rule->getConditions();

    $result = null;

    $local_version = id(new HeraldRule())->getConfigVersion();
    if ($rule->getConfigVersion() > $local_version) {
      $reason = pht(
        'Rule could not be processed, it was created with a newer version '.
        'of Herald.');
      $result = false;
    } else if (!$conditions) {
      $reason = pht(
        'Rule failed automatically because it has no conditions.');
      $result = false;
    } else if (!$rule->hasValidAuthor()) {
      $reason = pht(
        'Rule failed automatically because its owner is invalid '.
        'or disabled.');
      $result = false;
    } else if (!$this->canAuthorViewObject($rule, $object)) {
      $reason = pht(
        'Rule failed automatically because it is a personal rule and its '.
        'owner can not see the object.');
      $result = false;
    } else if (!$this->canRuleApplyToObject($rule, $object)) {
      $reason = pht(
        'Rule failed automatically because it is an object rule which is '.
        'not relevant for this object.');
      $result = false;
    } else {
      foreach ($conditions as $condition) {
        try {
          $this->getConditionObjectValue($condition, $object);
        } catch (Exception $ex) {
          $reason = pht(
            'Field "%s" does not exist!',
            $condition->getFieldName());
          $result = false;
          break;
        }

        $match = $this->doesConditionMatch($rule, $condition, $object);

        if (!$all && $match) {
          $reason = pht('Any condition matched.');
          $result = true;
          break;
        }

        if ($all && !$match) {
          $reason = pht('Not all conditions matched.');
          $result = false;
          break;
        }
      }

      if ($result === null) {
        if ($all) {
          $reason = pht('All conditions matched.');
          $result = true;
        } else {
          $reason = pht('No conditions matched.');
          $result = false;
        }
      }
    }

    $rule_transcript = new HeraldRuleTranscript();
    $rule_transcript->setRuleID($rule->getID());
    $rule_transcript->setResult($result);
    $rule_transcript->setReason($reason);
    $rule_transcript->setRuleName($rule->getName());
    $rule_transcript->setRuleOwner($rule->getAuthorPHID());

    $this->transcript->addRuleTranscript($rule_transcript);

    return $result;
  }

  protected function doesConditionMatch(
    HeraldRule $rule,
    HeraldCondition $condition,
    HeraldAdapter $object) {

    $object_value = $this->getConditionObjectValue($condition, $object);
    $test_value   = $condition->getValue();

    $cond = $condition->getFieldCondition();

    $transcript = new HeraldConditionTranscript();
    $transcript->setRuleID($rule->getID());
    $transcript->setConditionID($condition->getID());
    $transcript->setFieldName($condition->getFieldName());
    $transcript->setCondition($cond);
    $transcript->setTestValue($test_value);

    try {
      $result = $object->doesConditionMatch(
        $this,
        $rule,
        $condition,
        $object_value);
    } catch (HeraldInvalidConditionException $ex) {
      $result = false;
      $transcript->setNote($ex->getMessage());
    }

    $transcript->setResult($result);

    $this->transcript->addConditionTranscript($transcript);

    return $result;
  }

  protected function getConditionObjectValue(
    HeraldCondition $condition,
    HeraldAdapter $object) {

    $field = $condition->getFieldName();

    return $this->getObjectFieldValue($field);
  }

  public function getObjectFieldValue($field) {
    if (!array_key_exists($field, $this->fieldCache)) {
      $this->fieldCache[$field] = $this->object->getHeraldField($field);
    }

    return $this->fieldCache[$field];
  }

  protected function getRuleEffects(
    HeraldRule $rule,
    HeraldAdapter $object) {

    $effects = array();
    foreach ($rule->getActions() as $action) {
      $effect = id(new HeraldEffect())
        ->setObjectPHID($object->getPHID())
        ->setAction($action->getAction())
        ->setTarget($action->getTarget())
        ->setRule($rule);

      $name = $rule->getName();
      $id = $rule->getID();
      $effect->setReason(
        pht(
          'Conditions were met for %s',
          "H{$id} {$name}"));

      $effects[] = $effect;
    }
    return $effects;
  }

  private function canAuthorViewObject(
    HeraldRule $rule,
    HeraldAdapter $adapter) {

    // Authorship is irrelevant for global rules and object rules.
    if ($rule->isGlobalRule() || $rule->isObjectRule()) {
      return true;
    }

    // The author must be able to create rules for the adapter's content type.
    // In particular, this means that the application must be installed and
    // accessible to the user. For example, if a user writes a Differential
    // rule and then loses access to Differential, this disables the rule.
    $enabled = HeraldAdapter::getEnabledAdapterMap($rule->getAuthor());
    if (empty($enabled[$adapter->getAdapterContentType()])) {
      return false;
    }

    // Finally, the author must be able to see the object itself. You can't
    // write a personal rule that CC's you on revisions you wouldn't otherwise
    // be able to see, for example.
    $object = $adapter->getObject();
    return PhabricatorPolicyFilter::hasCapability(
      $rule->getAuthor(),
      $object,
      PhabricatorPolicyCapability::CAN_VIEW);
  }

  private function canRuleApplyToObject(
    HeraldRule $rule,
    HeraldAdapter $adapter) {

    // Rules which are not object rules can apply to anything.
    if (!$rule->isObjectRule()) {
      return true;
    }

    $trigger_phid = $rule->getTriggerObjectPHID();
    $object_phids = $adapter->getTriggerObjectPHIDs();

    if ($object_phids) {
      if (in_array($trigger_phid, $object_phids)) {
        return true;
      }
    }

    return false;
  }

}
