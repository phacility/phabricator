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

  private $forbiddenFields = array();
  private $forbiddenActions = array();
  private $skipEffects = array();

  private $profilerStack = array();
  private $profilerFrames = array();

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

      $is_first_only = $rule->isRepeatFirst();

      try {
        if (!$this->getDryRun() &&
            $is_first_only &&
            $rule->getRuleApplied($object->getPHID())) {
          // This is not a dry run, and this rule is only supposed to be
          // applied a single time, and it's already been applied...
          // That means automatic failure.
          $this->newRuleTranscript($rule)
            ->setResult(false)
            ->setReason(
              pht(
                'This rule is only supposed to be repeated a single time, '.
                'and it has already been applied.'));

          $rule_matches = false;
        } else {
          if ($this->isForbidden($rule, $object)) {
            $this->newRuleTranscript($rule)
              ->setResult(HeraldRuleTranscript::RESULT_FORBIDDEN)
              ->setReason(
                pht(
                  'Object state is not compatible with rule.'));

            $rule_matches = false;
          } else {
            $rule_matches = $this->doesRuleMatch($rule, $object);
          }
        }
      } catch (HeraldRecursiveConditionsException $ex) {
        $names = array();
        foreach ($this->stack as $rule_phid => $ignored) {
          $names[] = '"'.$rules[$rule_phid]->getName().'"';
        }
        $names = implode(', ', $names);
        foreach ($this->stack as $rule_phid => $ignored) {
          $this->newRuleTranscript($rules[$rule_phid])
            ->setResult(false)
            ->setReason(
              pht(
                "Rules %s are recursively dependent upon one another! ".
                "Don't do this! You have formed an unresolvable cycle in the ".
                "dependency graph!",
                $names));
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

    $xaction_phids = null;
    $xactions = $object->getAppliedTransactions();
    if ($xactions !== null) {
      $xaction_phids = mpull($xactions, 'getPHID');
    }

    $object_transcript = id(new HeraldObjectTranscript())
      ->setPHID($object->getPHID())
      ->setName($object->getHeraldName())
      ->setType($object->getAdapterContentType())
      ->setFields($this->fieldCache)
      ->setAppliedTransactionPHIDs($xaction_phids)
      ->setProfile($this->getProfile());

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

    // Update the "applied" state table. How this table works depends on the
    // repetition policy for the rule.
    //
    // REPEAT_EVERY: We delete existing rows for the rule, then write nothing.
    // This policy doesn't use any state.
    //
    // REPEAT_FIRST: We keep existing rows, then write additional rows for
    // rules which fired. This policy accumulates state over the life of the
    // object.
    //
    // REPEAT_CHANGE: We delete existing rows, then write all the rows which
    // matched. This policy only uses the state from the previous run.

    $rules = mpull($rules, null, 'getID');
    $rule_ids = mpull($xscripts, 'getRuleID');

    $delete_ids = array();
    foreach ($rules as $rule_id => $rule) {
      if ($rule->isRepeatFirst()) {
        continue;
      }
      $delete_ids[] = $rule_id;
    }

    $applied_ids = array();
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

      if ($rule->isRepeatFirst() || $rule->isRepeatOnChange()) {
        $applied_ids[] = $rule_id;
      }
    }

    // Also include "only if this rule did not match the last time" rules
    // which matched but were skipped in the "applied" list.
    foreach ($this->skipEffects as $rule_id => $ignored) {
      $applied_ids[] = $rule_id;
    }

    if ($delete_ids || $applied_ids) {
      $conn_w = id(new HeraldRule())->establishConnection('w');

      if ($delete_ids) {
        queryfx(
          $conn_w,
          'DELETE FROM %T WHERE phid = %s AND ruleID IN (%Ld)',
          HeraldRule::TABLE_RULE_APPLIED,
          $adapter->getPHID(),
          $delete_ids);
      }

      if ($applied_ids) {
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
          'INSERT IGNORE INTO %T (phid, ruleID) VALUES %LQ',
          HeraldRule::TABLE_RULE_APPLIED,
          $sql);
      }
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

        // Here, we're profiling the cost to match the condition value against
        // whatever test is configured. Normally, this cost should be very
        // small (<<1ms) since it amounts to a single comparison:
        //
        //   [ Task author ][ is any of ][ alice ]
        //
        // However, it may be expensive in some cases, particularly if you
        // write a rule with a very creative regular expression that backtracks
        // explosively.
        //
        // At time of writing, the "Another Herald Rule" field is also
        // evaluated inside the matching function. This may be arbitrarily
        // expensive (it can prompt us to execute any finite number of other
        // Herald rules), although we'll push the profiler stack appropriately
        // so we don't count the evaluation time against this rule in the final
        // profile.

        $caught = null;

        $this->pushProfilerRule($rule);
        try {
          $match = $this->doesConditionMatch($rule, $condition, $object);
        } catch (Exception $ex) {
          $caught = $ex;
        }
        $this->popProfilerRule($rule);

        if ($caught) {
          throw $ex;
        }

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

    // If this rule matched, and is set to run "if it did not match the last
    // time", and we matched the last time, we're going to return a match in
    // the transcript but set a flag so we don't actually apply any effects.

    // We need the rule to match so that storage gets updated properly. If we
    // just pretend the rule didn't match it won't cause any effects (which
    // is correct), but it also won't set the "it matched" flag in storage,
    // so the next run after this one would incorrectly trigger again.

    $is_dry_run = $this->getDryRun();
    if ($result && !$is_dry_run) {
      $is_on_change = $rule->isRepeatOnChange();
      if ($is_on_change) {
        $did_apply = $rule->getRuleApplied($object->getPHID());
        if ($did_apply) {
          $reason = pht(
            'This rule matched, but did not take any actions because it '.
            'is configured to act only if it did not match the last time.');

          $this->skipEffects[$rule->getID()] = true;
        }
      }
    }

    $this->newRuleTranscript($rule)
      ->setResult($result)
      ->setReason($reason);

    return $result;
  }

  protected function doesConditionMatch(
    HeraldRule $rule,
    HeraldCondition $condition,
    HeraldAdapter $object) {

    $object_value = $this->getConditionObjectValue($condition, $object);
    $transcript = $this->newConditionTranscript($rule, $condition);

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
      $adapter = $this->object;

      $adapter->willGetHeraldField($field);

      $caught = null;

      $this->pushProfilerField($field);
      try {
        $value = $adapter->getHeraldField($field);
      } catch (Exception $ex) {
        $caught = $ex;
      }
      $this->popProfilerField($field);

      if ($caught) {
        throw $caught;
      }

      $this->fieldCache[$field] = $value;
    }

    return $this->fieldCache[$field];
  }

  protected function getRuleEffects(
    HeraldRule $rule,
    HeraldAdapter $object) {

    $rule_id = $rule->getID();
    if (isset($this->skipEffects[$rule_id])) {
      return array();
    }

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

  private function newRuleTranscript(HeraldRule $rule) {
    $xscript = id(new HeraldRuleTranscript())
      ->setRuleID($rule->getID())
      ->setRuleName($rule->getName())
      ->setRuleOwner($rule->getAuthorPHID());

    $this->transcript->addRuleTranscript($xscript);

    return $xscript;
  }

  private function newConditionTranscript(
    HeraldRule $rule,
    HeraldCondition $condition) {

    $xscript = id(new HeraldConditionTranscript())
      ->setRuleID($rule->getID())
      ->setConditionID($condition->getID())
      ->setFieldName($condition->getFieldName())
      ->setCondition($condition->getFieldCondition())
      ->setTestValue($condition->getValue());

    $this->transcript->addConditionTranscript($xscript);

    return $xscript;
  }

  private function newApplyTranscript(
    HeraldAdapter $adapter,
    HeraldRule $rule,
    HeraldActionRecord $action) {

    $effect = id(new HeraldEffect())
      ->setObjectPHID($adapter->getPHID())
      ->setAction($action->getAction())
      ->setTarget($action->getTarget())
      ->setRule($rule);

    $xscript = new HeraldApplyTranscript($effect, false);

    $this->transcript->addApplyTranscript($xscript);

    return $xscript;
  }

  private function isForbidden(
    HeraldRule $rule,
    HeraldAdapter $adapter) {

    $forbidden = $adapter->getForbiddenActions();
    if (!$forbidden) {
      return false;
    }

    $forbidden = array_fuse($forbidden);

    $is_forbidden = false;

    foreach ($rule->getConditions() as $condition) {
      $field_key = $condition->getFieldName();

      if (!isset($this->forbiddenFields[$field_key])) {
        $reason = null;

        try {
          $states = $adapter->getRequiredFieldStates($field_key);
        } catch (Exception $ex) {
          $states = array();
        }

        foreach ($states as $state) {
          if (!isset($forbidden[$state])) {
            continue;
          }
          $reason = $adapter->getForbiddenReason($state);
          break;
        }

        $this->forbiddenFields[$field_key] = $reason;
      }

      $forbidden_reason = $this->forbiddenFields[$field_key];
      if ($forbidden_reason !== null) {
        $this->newConditionTranscript($rule, $condition)
          ->setResult(HeraldConditionTranscript::RESULT_FORBIDDEN)
          ->setNote($forbidden_reason);

        $is_forbidden = true;
      }
    }

    foreach ($rule->getActions() as $action_record) {
      $action_key = $action_record->getAction();

      if (!isset($this->forbiddenActions[$action_key])) {
        $reason = null;

        try {
          $states = $adapter->getRequiredActionStates($action_key);
        } catch (Exception $ex) {
          $states = array();
        }

        foreach ($states as $state) {
          if (!isset($forbidden[$state])) {
            continue;
          }
          $reason = $adapter->getForbiddenReason($state);
          break;
        }

        $this->forbiddenActions[$action_key] = $reason;
      }

      $forbidden_reason = $this->forbiddenActions[$action_key];
      if ($forbidden_reason !== null) {
        $this->newApplyTranscript($adapter, $rule, $action_record)
          ->setAppliedReason(
            array(
              array(
                'type' => HeraldAction::DO_STANDARD_FORBIDDEN,
                'data' => $forbidden_reason,
              ),
            ));

        $is_forbidden = true;
      }
    }

    return $is_forbidden;
  }

/* -(  Profiler  )----------------------------------------------------------- */

  private function pushProfilerField($field_key) {
    return $this->pushProfilerStack('field', $field_key);
  }

  private function popProfilerField($field_key) {
    return $this->popProfilerStack('field', $field_key);
  }

  private function pushProfilerRule(HeraldRule $rule) {
    return $this->pushProfilerStack('rule', $rule->getPHID());
  }

  private function popProfilerRule(HeraldRule $rule) {
    return $this->popProfilerStack('rule', $rule->getPHID());
  }

  private function pushProfilerStack($type, $key) {
    $this->profilerStack[] = array(
      'type' => $type,
      'key' => $key,
      'start' => microtime(true),
    );

    return $this;
  }

  private function popProfilerStack($type, $key) {
    if (!$this->profilerStack) {
      throw new Exception(
        pht(
          'Unable to pop profiler stack: profiler stack is empty.'));
    }

    $frame = last($this->profilerStack);
    if (($frame['type'] !== $type) || ($frame['key'] !== $key)) {
      throw new Exception(
        pht(
          'Unable to pop profiler stack: expected frame of type "%s" with '.
          'key "%s", but found frame of type "%s" with key "%s".',
          $type,
          $key,
          $frame['type'],
          $frame['key']));
    }

    // Accumulate the new timing information into the existing profile. If this
    // is the first time we've seen this particular rule or field, we'll
    // create a new empty frame first.

    $elapsed = microtime(true) - $frame['start'];
    $frame_key = sprintf('%s/%s', $type, $key);

    if (!isset($this->profilerFrames[$frame_key])) {
      $current = array(
        'type' => $type,
        'key' => $key,
        'elapsed' => 0,
        'count' => 0,
      );
    } else {
      $current = $this->profilerFrames[$frame_key];
    }

    $current['elapsed'] += $elapsed;
    $current['count']++;

    $this->profilerFrames[$frame_key] = $current;

    array_pop($this->profilerStack);
  }

  private function getProfile() {
    if ($this->profilerStack) {
      $frame = last($this->profilerStack);
      $frame_type = $frame['type'];
      $frame_key = $frame['key'];
      $frame_count = count($this->profilerStack);

      throw new Exception(
        pht(
          'Unable to retrieve profile: profiler stack is not empty. The '.
          'stack has %s frame(s); the final frame has type "%s" and key '.
          '"%s".',
          new PhutilNumber($frame_count),
          $frame_type,
          $frame_key));
    }

    return array_values($this->profilerFrames);
  }


}
