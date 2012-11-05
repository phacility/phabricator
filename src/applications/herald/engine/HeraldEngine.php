<?php

final class HeraldEngine {

  protected $rules = array();
  protected $results = array();
  protected $stack = array();
  protected $activeRule = null;

  protected $fieldCache = array();
  protected $object = null;

  public static function loadAndApplyRules(HeraldObjectAdapter $object) {
    $content_type = $object->getHeraldTypeName();
    $rules = HeraldRule::loadAllByContentTypeWithFullData(
      $content_type,
      $object->getPHID());

    $engine = new HeraldEngine();
    $effects = $engine->applyRules($rules, $object);
    $engine->applyEffects($effects, $object, $rules);

    return $engine->getTranscript();
  }

  public function applyRules(array $rules, HeraldObjectAdapter $object) {
    assert_instances_of($rules, 'HeraldRule');
    $t_start = microtime(true);

    $rules = mpull($rules, null, 'getID');

    $this->transcript = new HeraldTranscript();
    $this->transcript->setObjectPHID((string)$object->getPHID());
    $this->fieldCache = array();
    $this->results = array();
    $this->rules   = $rules;
    $this->object  = $object;

    $effects = array();
    foreach ($rules as $id => $rule) {
      $this->stack = array();
      try {
        if (($rule->getRepetitionPolicy() ==
             HeraldRepetitionPolicyConfig::FIRST) &&
            $rule->getRuleApplied($object->getPHID())) {
          // This rule is only supposed to be applied a single time, and it's
          // aleady been applied, so this is an automatic failure.
          $xscript = id(new HeraldRuleTranscript())
            ->setRuleID($id)
            ->setResult(false)
            ->setRuleName($rule->getName())
            ->setRuleOwner($rule->getAuthorPHID())
            ->setReason(
              "This rule is only supposed to be repeated a single time, ".
              "and it has already been applied."
            );
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
            "Rules {$names} are recursively dependent upon one another! ".
            "Don't do this! You have formed an unresolvable cycle in the ".
            "dependency graph!");
          $xscript->setRuleName($rules[$rule_id]->getName());
          $xscript->setRuleOwner($rules[$rule_id]->getAuthorPHID());
          $this->transcript->addRuleTranscript($xscript);
        }
        $rule_matches = false;
      }
      $this->results[$id] = $rule_matches;

      if ($rule_matches) {
        foreach ($this->getRuleEffects($rule, $object) as $effect) {
          $effects[] = $effect;
        }
      }
    }

    $object_transcript = new HeraldObjectTranscript();
    $object_transcript->setPHID($object->getPHID());
    $object_transcript->setName($object->getHeraldName());
    $object_transcript->setType($object->getHeraldTypeName());
    $object_transcript->setFields($this->fieldCache);

    $this->transcript->setObjectTranscript($object_transcript);

    $t_end = microtime(true);

    $this->transcript->setDuration($t_end - $t_start);

    return $effects;
  }

  public function applyEffects(
    array $effects,
    HeraldObjectAdapter $object,
    array $rules) {
    assert_instances_of($effects, 'HeraldEffect');
    assert_instances_of($rules, 'HeraldRule');

    $this->transcript->setDryRun($object instanceof HeraldDryRunAdapter);

    $xscripts = $object->applyHeraldEffects($effects);
    foreach ($xscripts as $apply_xscript) {
      if (!($apply_xscript instanceof HeraldApplyTranscript)) {
        throw new Exception(
          "Heraldable must return HeraldApplyTranscripts from ".
          "applyHeraldEffect().");
      }
      $this->transcript->addApplyTranscript($apply_xscript);
    }

    if (!$this->transcript->getDryRun()) {

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
            $object->getPHID(),
            $id);
        }
        queryfx(
          $conn_w,
          'INSERT IGNORE INTO %T (phid, ruleID) VALUES %Q',
          HeraldRule::TABLE_RULE_APPLIED,
          implode(', ', $sql));
      }
    }
  }

  public function getTranscript() {
    $this->transcript->save();
    return $this->transcript;
  }

  protected function doesRuleMatch(
    HeraldRule $rule,
    HeraldObjectAdapter $object) {

    $id = $rule->getID();

    if (isset($this->results[$id])) {
      // If we've already evaluated this rule because another rule depends
      // on it, we don't need to reevaluate it.
      return $this->results[$id];
    }

    if (isset($this->stack[$id])) {
      // We've recursed, fail all of the rules on the stack. This happens when
      // there's a dependency cycle with "Rule conditions match for rule ..."
      // conditions.
      foreach ($this->stack as $rule_id => $ignored) {
        $this->results[$rule_id] = false;
      }
      throw new HeraldRecursiveConditionsException();
    }

    $this->stack[$id] = true;

    $all = $rule->getMustMatchAll();

    $conditions = $rule->getConditions();

    $result = null;

    $local_version = id(new HeraldRule())->getConfigVersion();
    if ($rule->getConfigVersion() > $local_version) {
      $reason = "Rule could not be processed, it was created with a newer ".
                "version of Herald.";
      $result = false;
    } else if (!$conditions) {
      $reason = "Rule failed automatically because it has no conditions.";
      $result = false;
    } else if ($rule->hasInvalidOwner()) {
      $reason = "Rule failed automatically because its owner is invalid ".
                "or disabled.";
      $result = false;
    } else {
      foreach ($conditions as $condition) {
        $match = $this->doesConditionMatch($rule, $condition, $object);

        if (!$all && $match) {
          $reason = "Any condition matched.";
          $result = true;
          break;
        }

        if ($all && !$match) {
          $reason = "Not all conditions matched.";
          $result = false;
          break;
        }
      }

      if ($result === null) {
        if ($all) {
          $reason = "All conditions matched.";
          $result = true;
        } else {
          $reason = "No conditions matched.";
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
    HeraldObjectAdapter $object) {

    $object_value = $this->getConditionObjectValue($condition, $object);
    $test_value   = $condition->getValue();

    $cond = $condition->getFieldCondition();

    $transcript = new HeraldConditionTranscript();
    $transcript->setRuleID($rule->getID());
    $transcript->setConditionID($condition->getID());
    $transcript->setFieldName($condition->getFieldName());
    $transcript->setCondition($cond);
    $transcript->setTestValue($test_value);

    $result = null;
    switch ($cond) {
      case HeraldConditionConfig::CONDITION_CONTAINS:
        // "Contains" can take an array of strings, as in "Any changed
        // filename" for diffs.
        foreach ((array)$object_value as $value) {
          $result = (stripos($value, $test_value) !== false);
          if ($result) {
            break;
          }
        }
        break;
      case HeraldConditionConfig::CONDITION_NOT_CONTAINS:
        $result = (stripos($object_value, $test_value) === false);
        break;
      case HeraldConditionConfig::CONDITION_IS:
        $result = ($object_value == $test_value);
        break;
      case HeraldConditionConfig::CONDITION_IS_NOT:
        $result = ($object_value != $test_value);
        break;
      case HeraldConditionConfig::CONDITION_IS_ME:
        $result = ($object_value == $rule->getAuthorPHID());
        break;
      case HeraldConditionConfig::CONDITION_IS_NOT_ME:
        $result = ($object_value != $rule->getAuthorPHID());
        break;
      case HeraldConditionConfig::CONDITION_IS_ANY:
        $test_value = array_flip($test_value);
        $result = isset($test_value[$object_value]);
        break;
      case HeraldConditionConfig::CONDITION_IS_NOT_ANY:
        $test_value = array_flip($test_value);
        $result = !isset($test_value[$object_value]);
        break;
      case HeraldConditionConfig::CONDITION_INCLUDE_ALL:
        if (!is_array($object_value)) {
          $transcript->setNote('Object produced bad value!');
          $result = false;
        } else {
          $have = array_select_keys(array_flip($object_value),
                                    $test_value);
          $result = (count($have) == count($test_value));
        }
        break;
      case HeraldConditionConfig::CONDITION_INCLUDE_ANY:
        $result = (bool)array_select_keys(array_flip($object_value),
                                          $test_value);
        break;
      case HeraldConditionConfig::CONDITION_INCLUDE_NONE:
        $result = !array_select_keys(array_flip($object_value),
                                     $test_value);
        break;
      case HeraldConditionConfig::CONDITION_EXISTS:
        $result = (bool)$object_value;
        break;
      case HeraldConditionConfig::CONDITION_NOT_EXISTS:
        $result = !$object_value;
        break;
      case HeraldConditionConfig::CONDITION_REGEXP:
        foreach ((array)$object_value as $value) {
          // We add the 'S' flag because we use the regexp multiple times.
          // It shouldn't cause any troubles if the flag is already there
          // - /.*/S is evaluated same as /.*/SS.
          $result = @preg_match($test_value . 'S', $value);
          if ($result === false) {
            $transcript->setNote(
              "Regular expression is not valid!");
            break;
          }
          if ($result) {
            break;
          }
        }
        $result = (bool)$result;
        break;
      case HeraldConditionConfig::CONDITION_REGEXP_PAIR:
        // Match a JSON-encoded pair of regular expressions against a
        // dictionary. The first regexp must match the dictionary key, and the
        // second regexp must match the dictionary value. If any key/value pair
        // in the dictionary matches both regexps, the condition is satisfied.
        $regexp_pair = json_decode($test_value, true);
        if (!is_array($regexp_pair)) {
          $result = false;
          $transcript->setNote("Regular expression pair is not valid JSON!");
          break;
        }
        if (count($regexp_pair) != 2) {
          $result = false;
          $transcript->setNote("Regular expression pair is not a pair!");
          break;
        }

        $key_regexp   = array_shift($regexp_pair);
        $value_regexp = array_shift($regexp_pair);

        foreach ((array)$object_value as $key => $value) {
          $key_matches = @preg_match($key_regexp, $key);
          if ($key_matches === false) {
            $result = false;
            $transcript->setNote("First regular expression is invalid!");
            break 2;
          }
          if ($key_matches) {
            $value_matches = @preg_match($value_regexp, $value);
            if ($value_matches === false) {
              $result = false;
              $transcript->setNote("Second regular expression is invalid!");
              break 2;
            }
            if ($value_matches) {
              $result = true;
              break 2;
            }
          }
        }
        $result = false;
        break;
      case HeraldConditionConfig::CONDITION_RULE:
      case HeraldConditionConfig::CONDITION_NOT_RULE:

        $rule = idx($this->rules, $test_value);
        if (!$rule) {
          $transcript->setNote(
            "Condition references a rule which does not exist!");
          $result = false;
        } else {
          $is_not = ($cond == HeraldConditionConfig::CONDITION_NOT_RULE);
          $result = $this->doesRuleMatch($rule, $object);
          if ($is_not) {
            $result = !$result;
          }
        }
        break;
      default:
        throw new HeraldInvalidConditionException(
          "Unknown condition '{$cond}'.");
    }

    $transcript->setResult($result);

    $this->transcript->addConditionTranscript($transcript);

    return $result;
  }

  protected function getConditionObjectValue(
    HeraldCondition $condition,
    HeraldObjectAdapter $object) {

    $field = $condition->getFieldName();

    return $this->getObjectFieldValue($field);
  }

  public function getObjectFieldValue($field) {
    if (isset($this->fieldCache[$field])) {
      return $this->fieldCache[$field];
    }

    $result = null;
    switch ($field) {
      case HeraldFieldConfig::FIELD_RULE:
        $result = null;
        break;
      case HeraldFieldConfig::FIELD_TITLE:
      case HeraldFieldConfig::FIELD_BODY:
      case HeraldFieldConfig::FIELD_DIFF_FILE:
      case HeraldFieldConfig::FIELD_DIFF_CONTENT:
        // TODO: Type should be string.
        $result = $this->object->getHeraldField($field);
        break;
      case HeraldFieldConfig::FIELD_AUTHOR:
      case HeraldFieldConfig::FIELD_REPOSITORY:
      case HeraldFieldConfig::FIELD_MERGE_REQUESTER:
        // TODO: Type should be PHID.
        $result = $this->object->getHeraldField($field);
        break;
      case HeraldFieldConfig::FIELD_TAGS:
      case HeraldFieldConfig::FIELD_REVIEWER:
      case HeraldFieldConfig::FIELD_REVIEWERS:
      case HeraldFieldConfig::FIELD_CC:
      case HeraldFieldConfig::FIELD_DIFFERENTIAL_REVIEWERS:
      case HeraldFieldConfig::FIELD_DIFFERENTIAL_CCS:
        // TODO: Type should be list.
        $result = $this->object->getHeraldField($field);
        break;
      case HeraldFieldConfig::FIELD_AFFECTED_PACKAGE:
      case HeraldFieldConfig::FIELD_AFFECTED_PACKAGE_OWNER:
      case HeraldFieldConfig::FIELD_NEED_AUDIT_FOR_PACKAGE:
        $result = $this->object->getHeraldField($field);
        if (!is_array($result)) {
          throw new HeraldInvalidFieldException(
            "Value of field type {$field} is not an array!");
        }
        break;
      case HeraldFieldConfig::FIELD_DIFFERENTIAL_REVISION:
        // TODO: Type should be boolean I guess.
        $result = $this->object->getHeraldField($field);
        break;
      default:
        throw new HeraldInvalidConditionException(
          "Unknown field type '{$field}'!");
    }

    $this->fieldCache[$field] = $result;
    return $result;
  }

  protected function getRuleEffects(
    HeraldRule $rule,
    HeraldObjectAdapter $object) {

    $effects = array();
    foreach ($rule->getActions() as $action) {
      $effect = new HeraldEffect();
      $effect->setObjectPHID($object->getPHID());
      $effect->setAction($action->getAction());
      $effect->setTarget($action->getTarget());

      $effect->setRuleID($rule->getID());

      $name = $rule->getName();
      $id   = $rule->getID();
      $effect->setReason(
        'Conditions were met for Herald rule "'.$name.'" (#'.$id.').');

      $effects[] = $effect;
    }
    return $effects;
  }

}
