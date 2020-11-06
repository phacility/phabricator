<?php

final class HeraldEngine extends Phobject {

  protected $rules = array();
  protected $activeRule;
  protected $transcript;

  private $fieldCache = array();
  private $fieldExceptions = array();
  protected $object;
  private $dryRun;

  private $forbiddenFields = array();
  private $forbiddenActions = array();
  private $skipEffects = array();

  private $profilerStack = array();
  private $profilerFrames = array();

  private $ruleResults;
  private $ruleStack;

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

/* -(  Rule Stack  )--------------------------------------------------------- */

  private function resetRuleStack() {
    $this->ruleStack = array();
    return $this;
  }

  private function hasRuleOnStack(HeraldRule $rule) {
    $phid = $rule->getPHID();
    return isset($this->ruleStack[$phid]);
  }

  private function pushRuleStack(HeraldRule $rule) {
    $phid = $rule->getPHID();
    $this->ruleStack[$phid] = $rule;
    return $this;
  }

  private function getRuleStack() {
    return array_values($this->ruleStack);
  }

/* -(  Rule Results  )------------------------------------------------------- */

  private function resetRuleResults() {
    $this->ruleResults = array();
    return $this;
  }

  private function setRuleResult(
    HeraldRule $rule,
    HeraldRuleResult $result) {

    $phid = $rule->getPHID();

    if ($this->hasRuleResult($rule)) {
      throw new Exception(
        pht(
          'Herald rule "%s" already has an evaluation result.',
          $phid));
    }

    $this->ruleResults[$phid] = $result;

    $this->newRuleTranscript($rule)
      ->setRuleResult($result);

    return $this;
  }

  private function hasRuleResult(HeraldRule $rule) {
    $phid = $rule->getPHID();
    return isset($this->ruleResults[$phid]);
  }

  private function getRuleResult(HeraldRule $rule) {
    $phid = $rule->getPHID();

    if (!$this->hasRuleResult($rule)) {
      throw new Exception(
        pht(
          'Herald rule "%s" does not have an evaluation result.',
          $phid));
    }

    return $this->ruleResults[$phid];
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
    $this->fieldExceptions = array();
    $this->rules   = $rules;
    $this->object  = $object;

    $this->resetRuleResults();

    $effects = array();
    foreach ($rules as $phid => $rule) {
      $this->resetRuleStack();

      $caught = null;
      $result = null;
      try {
        $is_first_only = $rule->isRepeatFirst();

        if (!$this->getDryRun() &&
            $is_first_only &&
            $rule->getRuleApplied($object->getPHID())) {

          // This is not a dry run, and this rule is only supposed to be
          // applied a single time, and it has already been applied.
          // That means automatic failure.

          $result_code = HeraldRuleResult::RESULT_ALREADY_APPLIED;
          $result = HeraldRuleResult::newFromResultCode($result_code);
        } else if ($this->isForbidden($rule, $object)) {
          $result_code = HeraldRuleResult::RESULT_OBJECT_STATE;
          $result = HeraldRuleResult::newFromResultCode($result_code);
        } else {
          $result = $this->getRuleMatchResult($rule, $object);
        }
      } catch (HeraldRecursiveConditionsException $ex) {
        $cycle_phids = array();

        $stack = $this->getRuleStack();
        foreach ($stack as $stack_rule) {
          $cycle_phids[] = $stack_rule->getPHID();
        }
        // Add the rule which actually cycled to the list to make the
        // result more clear when we show it to the user.
        $cycle_phids[] = $phid;

        foreach ($stack as $stack_rule) {
          if ($this->hasRuleResult($stack_rule)) {
            continue;
          }

          $result_code = HeraldRuleResult::RESULT_RECURSION;
          $result_data = array(
            'cyclePHIDs' => $cycle_phids,
          );

          $result = HeraldRuleResult::newFromResultCode($result_code)
            ->setResultData($result_data);
          $this->setRuleResult($stack_rule, $result);
        }

        $result = $this->getRuleResult($rule);
      } catch (HeraldRuleEvaluationException $ex) {
        // When we encounter an evaluation exception, the condition which
        // failed to evaluate is responsible for logging the details of the
        // error.

        $result_code = HeraldRuleResult::RESULT_EVALUATION_EXCEPTION;
        $result = HeraldRuleResult::newFromResultCode($result_code);
      } catch (Exception $ex) {
        $caught = $ex;
      } catch (Throwable $ex) {
        $caught = $ex;
      }

      if ($caught) {
        // These exceptions are unexpected, and did not arise during rule
        // evaluation, so we're responsible for handling the details.

        $result_code = HeraldRuleResult::RESULT_EXCEPTION;

        $result_data = array(
          'exception.class' => get_class($caught),
          'exception.message' => $ex->getMessage(),
        );

        $result = HeraldRuleResult::newFromResultCode($result_code)
          ->setResultData($result_data);
      }

      if (!$this->hasRuleResult($rule)) {
        $this->setRuleResult($rule, $result);
      }
      $result = $this->getRuleResult($rule);

      if ($result->getShouldApplyActions()) {
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
    $result = $this->getRuleMatchResult($rule, $object);
    return $result->getShouldApplyActions();
  }

  private function getRuleMatchResult(
    HeraldRule $rule,
    HeraldAdapter $object) {

    if ($this->hasRuleResult($rule)) {
      // If we've already evaluated this rule because another rule depends
      // on it, we don't need to reevaluate it.
      return $this->getRuleResult($rule);
    }

    if ($this->hasRuleOnStack($rule)) {
      // We've recursed, fail all of the rules on the stack. This happens when
      // there's a dependency cycle with "Rule conditions match for rule ..."
      // conditions.
      throw new HeraldRecursiveConditionsException();
    }
    $this->pushRuleStack($rule);

    $all = $rule->getMustMatchAll();

    $conditions = $rule->getConditions();

    $result_code = null;
    $result_data = array();

    $local_version = id(new HeraldRule())->getConfigVersion();
    if ($rule->getConfigVersion() > $local_version) {
      $result_code = HeraldRuleResult::RESULT_VERSION;
    } else if (!$conditions) {
      $result_code = HeraldRuleResult::RESULT_EMPTY;
    } else if (!$rule->hasValidAuthor()) {
      $result_code = HeraldRuleResult::RESULT_OWNER;
    } else if (!$this->canAuthorViewObject($rule, $object)) {
      $result_code = HeraldRuleResult::RESULT_VIEW_POLICY;
    } else if (!$this->canRuleApplyToObject($rule, $object)) {
      $result_code = HeraldRuleResult::RESULT_OBJECT_RULE;
    } else {
      foreach ($conditions as $condition) {
        $caught = null;

        try {
          $match = $this->doesConditionMatch(
            $rule,
            $condition,
            $object);
        } catch (HeraldRuleEvaluationException $ex) {
          throw $ex;
        } catch (HeraldRecursiveConditionsException $ex) {
          throw $ex;
        } catch (Exception $ex) {
          $caught = $ex;
        } catch (Throwable $ex) {
          $caught = $ex;
        }

        if ($caught) {
          throw new HeraldRuleEvaluationException();
        }

        if (!$all && $match) {
          $result_code = HeraldRuleResult::RESULT_ANY_MATCHED;
          break;
        }

        if ($all && !$match) {
          $result_code = HeraldRuleResult::RESULT_ANY_FAILED;
          break;
        }
      }

      if ($result_code === null) {
        if ($all) {
          $result_code = HeraldRuleResult::RESULT_ALL_MATCHED;
        } else {
          $result_code = HeraldRuleResult::RESULT_ALL_FAILED;
        }
      }
    }

    // If this rule matched, and is set to run "if it did not match the last
    // time", and we matched the last time, we're going to return a special
    // result code which records a match but doesn't actually apply effects.

    // We need the rule to match so that storage gets updated properly. If we
    // just pretend the rule didn't match it won't cause any effects (which
    // is correct), but it also won't set the "it matched" flag in storage,
    // so the next run after this one would incorrectly trigger again.

    $result = HeraldRuleResult::newFromResultCode($result_code)
      ->setResultData($result_data);

    $should_apply = $result->getShouldApplyActions();

    $is_dry_run = $this->getDryRun();
    if ($should_apply && !$is_dry_run) {
      $is_on_change = $rule->isRepeatOnChange();
      if ($is_on_change) {
        $did_apply = $rule->getRuleApplied($object->getPHID());
        if ($did_apply) {
          // Replace the result with our modified result.
          $result_code = HeraldRuleResult::RESULT_LAST_MATCHED;
          $result = HeraldRuleResult::newFromResultCode($result_code);

          $this->skipEffects[$rule->getID()] = true;
        }
      }
    }

    $this->setRuleResult($rule, $result);

    return $result;
  }

  private function doesConditionMatch(
    HeraldRule $rule,
    HeraldCondition $condition,
    HeraldAdapter $adapter) {

    $transcript = $this->newConditionTranscript($rule, $condition);

    $caught = null;
    $result_data = array();

    try {
      $field_key = $condition->getFieldName();

      $field_value = $this->getProfiledObjectFieldValue(
        $adapter,
        $field_key);

      $is_match = $this->getProfiledConditionMatch(
        $adapter,
        $rule,
        $condition,
        $field_value);
      if ($is_match) {
        $result_code = HeraldConditionResult::RESULT_MATCHED;
      } else {
        $result_code = HeraldConditionResult::RESULT_FAILED;
      }
    } catch (HeraldRecursiveConditionsException $ex) {
      $result_code = HeraldConditionResult::RESULT_RECURSION;
      $caught = $ex;
    } catch (HeraldInvalidConditionException $ex) {
      $result_code = HeraldConditionResult::RESULT_INVALID;
      $caught = $ex;
    } catch (Exception $ex) {
      $result_code = HeraldConditionResult::RESULT_EXCEPTION;
      $caught = $ex;
    } catch (Throwable $ex) {
      $result_code = HeraldConditionResult::RESULT_EXCEPTION;
      $caught = $ex;
    }

    if ($caught) {
      $result_data = array(
        'exception.class' => get_class($caught),
        'exception.message' => $ex->getMessage(),
      );
    }

    $result = HeraldConditionResult::newFromResultCode($result_code)
      ->setResultData($result_data);

    $transcript->setResult($result);

    if ($caught) {
      throw $caught;
    }

    return $result->getIsMatch();
  }

  private function getProfiledConditionMatch(
    HeraldAdapter $adapter,
    HeraldRule $rule,
    HeraldCondition $condition,
    $field_value) {

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

    $this->pushProfilerRule($rule);

    $caught = null;
    try {
      $is_match = $adapter->doesConditionMatch(
        $this,
        $rule,
        $condition,
        $field_value);
    } catch (Exception $ex) {
      $caught = $ex;
    } catch (Throwable $ex) {
      $caught = $ex;
    }

    $this->popProfilerRule($rule);

    if ($caught) {
      throw $caught;
    }

    return $is_match;
  }

  private function getProfiledObjectFieldValue(
    HeraldAdapter $adapter,
    $field_key) {

    // Before engaging the profiler, make sure the field class is loaded.

    $adapter->willGetHeraldField($field_key);

    // The first time we read a field value, we'll actually generate it, which
    // may be slow.

    // After it is generated for the first time, this will just read it from a
    // cache, which should be very fast.

    // We still want to profile the request even if it goes to cache so we can
    // get an accurate count of how many times we access the field value: when
    // trying to improve the performance of Herald rules, it's helpful to know
    // how many rules rely on the value of a field which is slow to generate.

    $caught = null;

    $this->pushProfilerField($field_key);
    try {
      $value = $this->getObjectFieldValue($field_key);
    } catch (Exception $ex) {
      $caught = $ex;
    } catch (Throwable $ex) {
      $caught = $ex;
    }
    $this->popProfilerField($field_key);

    if ($caught) {
      throw $caught;
    }

    return $value;
  }

  private function getObjectFieldValue($field_key) {
    if (array_key_exists($field_key, $this->fieldExceptions)) {
      throw $this->fieldExceptions[$field_key];
    }

    if (array_key_exists($field_key, $this->fieldCache)) {
      return $this->fieldCache[$field_key];
    }

    $adapter = $this->object;

    $caught = null;
    try {
      $value = $adapter->getHeraldField($field_key);
    } catch (Exception $ex) {
      $caught = $ex;
    } catch (Throwable $ex) {
      $caught = $ex;
    }

    if ($caught) {
      $this->fieldExceptions[$field_key] = $caught;
      throw $caught;
    }

    $this->fieldCache[$field_key] = $value;

    return $value;
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
        $result_code = HeraldConditionResult::RESULT_OBJECT_STATE;
        $result_data = array(
          'reason' => $forbidden_reason,
        );

        $result = HeraldConditionResult::newFromResultCode($result_code)
          ->setResultData($result_data);

        $this->newConditionTranscript($rule, $condition)
          ->setResult($result);

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
