<?php

abstract class HeraldAdapter extends Phobject {

  const CONDITION_CONTAINS        = 'contains';
  const CONDITION_NOT_CONTAINS    = '!contains';
  const CONDITION_IS              = 'is';
  const CONDITION_IS_NOT          = '!is';
  const CONDITION_IS_ANY          = 'isany';
  const CONDITION_IS_NOT_ANY      = '!isany';
  const CONDITION_INCLUDE_ALL     = 'all';
  const CONDITION_INCLUDE_ANY     = 'any';
  const CONDITION_INCLUDE_NONE    = 'none';
  const CONDITION_IS_ME           = 'me';
  const CONDITION_IS_NOT_ME       = '!me';
  const CONDITION_REGEXP          = 'regexp';
  const CONDITION_NOT_REGEXP      = '!regexp';
  const CONDITION_RULE            = 'conditions';
  const CONDITION_NOT_RULE        = '!conditions';
  const CONDITION_EXISTS          = 'exists';
  const CONDITION_NOT_EXISTS      = '!exists';
  const CONDITION_UNCONDITIONALLY = 'unconditionally';
  const CONDITION_NEVER           = 'never';
  const CONDITION_REGEXP_PAIR     = 'regexp-pair';
  const CONDITION_HAS_BIT         = 'bit';
  const CONDITION_NOT_BIT         = '!bit';
  const CONDITION_IS_TRUE         = 'true';
  const CONDITION_IS_FALSE        = 'false';

  private $contentSource;
  private $isNewObject;
  private $applicationEmail;
  private $appliedTransactions = array();
  private $queuedTransactions = array();
  private $emailPHIDs = array();
  private $forcedEmailPHIDs = array();
  private $fieldMap;
  private $actionMap;
  private $edgeCache = array();
  private $forbiddenActions = array();
  private $viewer;
  private $mustEncryptReasons = array();
  private $actingAsPHID;
  private $webhookMap = array();

  public function getEmailPHIDs() {
    return array_values($this->emailPHIDs);
  }

  public function getForcedEmailPHIDs() {
    return array_values($this->forcedEmailPHIDs);
  }

  final public function setActingAsPHID($acting_as_phid) {
    $this->actingAsPHID = $acting_as_phid;
    return $this;
  }

  final public function getActingAsPHID() {
    return $this->actingAsPHID;
  }

  public function addEmailPHID($phid, $force) {
    $this->emailPHIDs[$phid] = $phid;
    if ($force) {
      $this->forcedEmailPHIDs[$phid] = $phid;
    }
    return $this;
  }

  public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  public function getViewer() {
    // See PHI276. Normally, Herald runs without regard for policy checks.
    // However, we use a real viewer during test console runs: this makes
    // intracluster calls to Diffusion APIs work even if web nodes don't
    // have privileged credentials.

    if ($this->viewer) {
      return $this->viewer;
    }

    return PhabricatorUser::getOmnipotentUser();
  }

  public function setContentSource(PhabricatorContentSource $content_source) {
    $this->contentSource = $content_source;
    return $this;
  }

  public function getContentSource() {
    return $this->contentSource;
  }

  public function getIsNewObject() {
    if (is_bool($this->isNewObject)) {
      return $this->isNewObject;
    }

    throw new Exception(
      pht(
        'You must %s to a boolean first!',
        'setIsNewObject()'));
  }
  public function setIsNewObject($new) {
    $this->isNewObject = (bool)$new;
    return $this;
  }

  public function supportsApplicationEmail() {
    return false;
  }

  public function setApplicationEmail(
    PhabricatorMetaMTAApplicationEmail $email) {
    $this->applicationEmail = $email;
    return $this;
  }

  public function getApplicationEmail() {
    return $this->applicationEmail;
  }

  public function getPHID() {
    return $this->getObject()->getPHID();
  }

  abstract public function getHeraldName();

  final public function willGetHeraldField($field_key) {
    // This method is called during rule evaluation, before we engage the
    // Herald profiler. We make sure we have a concrete implementation so time
    // spent loading fields out of the classmap is not mistakenly attributed to
    // whichever field happens to evaluate first.
    $this->requireFieldImplementation($field_key);
  }

  public function getHeraldField($field_key) {
    return $this->requireFieldImplementation($field_key)
      ->getHeraldFieldValue($this->getObject());
  }

  public function applyHeraldEffects(array $effects) {
    assert_instances_of($effects, 'HeraldEffect');

    $result = array();
    foreach ($effects as $effect) {
      $result[] = $this->applyStandardEffect($effect);
    }

    return $result;
  }

  public function isAvailableToUser(PhabricatorUser $viewer) {
    $applications = id(new PhabricatorApplicationQuery())
      ->setViewer($viewer)
      ->withInstalled(true)
      ->withClasses(array($this->getAdapterApplicationClass()))
      ->execute();

    return !empty($applications);
  }


  /**
   * Set the list of transactions which just took effect.
   *
   * These transactions are set by @{class:PhabricatorApplicationEditor}
   * automatically, before it invokes Herald.
   *
   * @param list<PhabricatorApplicationTransaction> List of transactions.
   * @return this
   */
  final public function setAppliedTransactions(array $xactions) {
    assert_instances_of($xactions, 'PhabricatorApplicationTransaction');
    $this->appliedTransactions = $xactions;
    return $this;
  }


  /**
   * Get a list of transactions which just took effect.
   *
   * When an object is edited normally, transactions are applied and then
   * Herald executes. You can call this method to examine the transactions
   * if you want to react to them.
   *
   * @return list<PhabricatorApplicationTransaction> List of transactions.
   */
  final public function getAppliedTransactions() {
    return $this->appliedTransactions;
  }

  final public function queueTransaction(
    PhabricatorApplicationTransaction $transaction) {
    $this->queuedTransactions[] = $transaction;
  }

  final public function getQueuedTransactions() {
    return $this->queuedTransactions;
  }

  final public function newTransaction() {
    $object = $this->newObject();

    if (!($object instanceof PhabricatorApplicationTransactionInterface)) {
      throw new Exception(
        pht(
          'Unable to build a new transaction for adapter object; it does '.
          'not implement "%s".',
          'PhabricatorApplicationTransactionInterface'));
    }

    $xaction = $object->getApplicationTransactionTemplate();

    if (!($xaction instanceof PhabricatorApplicationTransaction)) {
      throw new Exception(
        pht(
          'Expected object (of class "%s") to return a transaction template '.
          '(of class "%s"), but it returned something else ("%s").',
          get_class($object),
          'PhabricatorApplicationTransaction',
          phutil_describe_type($xaction)));
    }

    return $xaction;
  }


  /**
   * NOTE: You generally should not override this; it exists to support legacy
   * adapters which had hard-coded content types.
   */
  public function getAdapterContentType() {
    return get_class($this);
  }

  abstract public function getAdapterContentName();
  abstract public function getAdapterContentDescription();
  abstract public function getAdapterApplicationClass();
  abstract public function getObject();

  public function getAdapterContentIcon() {
    $application_class = $this->getAdapterApplicationClass();
    $application = newv($application_class, array());
    return $application->getIcon();
  }

  /**
   * Return a new characteristic object for this adapter.
   *
   * The adapter will use this object to test for interfaces, generate
   * transactions, and interact with custom fields.
   *
   * Adapters must return an object from this method to enable custom
   * field rules and various implicit actions.
   *
   * Normally, you'll return an empty version of the adapted object:
   *
   *   return new ApplicationObject();
   *
   * @return null|object Template object.
   */
  protected function newObject() {
    return null;
  }

  public function supportsRuleType($rule_type) {
    return false;
  }

  public function canTriggerOnObject($object) {
    return false;
  }

  public function isTestAdapterForObject($object) {
    return false;
  }

  public function canCreateTestAdapterForObject($object) {
    return $this->isTestAdapterForObject($object);
  }

  public function newTestAdapter(PhabricatorUser $viewer, $object) {
    return id(clone $this)
      ->setObject($object);
  }

  public function getAdapterTestDescription() {
    return null;
  }

  public function explainValidTriggerObjects() {
    return pht('This adapter can not trigger on objects.');
  }

  public function getTriggerObjectPHIDs() {
    return array($this->getPHID());
  }

  public function getAdapterSortKey() {
    return sprintf(
      '%08d%s',
      $this->getAdapterSortOrder(),
      $this->getAdapterContentName());
  }

  public function getAdapterSortOrder() {
    return 1000;
  }


/* -(  Fields  )------------------------------------------------------------- */

  private function getFieldImplementationMap() {
    if ($this->fieldMap === null) {
      // We can't use PhutilClassMapQuery here because field expansion
      // depends on the adapter and object.

      $object = $this->getObject();

      $map = array();
      $all = HeraldField::getAllFields();
      foreach ($all as $key => $field) {
        $field = id(clone $field)->setAdapter($this);

        if (!$field->supportsObject($object)) {
          continue;
        }
        $subfields = $field->getFieldsForObject($object);
        foreach ($subfields as $subkey => $subfield) {
          if (isset($map[$subkey])) {
            throw new Exception(
              pht(
                'Two HeraldFields (of classes "%s" and "%s") have the same '.
                'field key ("%s") after expansion for an object of class '.
                '"%s" inside adapter "%s". Each field must have a unique '.
                'field key.',
                get_class($subfield),
                get_class($map[$subkey]),
                $subkey,
                get_class($object),
                get_class($this)));
          }

          $subfield = id(clone $subfield)->setAdapter($this);

          $map[$subkey] = $subfield;
        }
      }
      $this->fieldMap = $map;
    }

    return $this->fieldMap;
  }

  private function getFieldImplementation($key) {
    return idx($this->getFieldImplementationMap(), $key);
  }

  public function getFields() {
    return array_keys($this->getFieldImplementationMap());
  }

  public function getFieldNameMap() {
    return mpull($this->getFieldImplementationMap(), 'getHeraldFieldName');
  }

  public function getFieldGroupKey($field_key) {
    $field = $this->getFieldImplementation($field_key);

    if (!$field) {
      return null;
    }

    return $field->getFieldGroupKey();
  }

  public function isFieldAvailable($field_key) {
    $field = $this->getFieldImplementation($field_key);

    if (!$field) {
      return null;
    }

    return $field->isFieldAvailable();
  }


/* -(  Conditions  )--------------------------------------------------------- */


  public function getConditionNameMap() {
    return array(
      self::CONDITION_CONTAINS        => pht('contains'),
      self::CONDITION_NOT_CONTAINS    => pht('does not contain'),
      self::CONDITION_IS              => pht('is'),
      self::CONDITION_IS_NOT          => pht('is not'),
      self::CONDITION_IS_ANY          => pht('is any of'),
      self::CONDITION_IS_TRUE         => pht('is true'),
      self::CONDITION_IS_FALSE        => pht('is false'),
      self::CONDITION_IS_NOT_ANY      => pht('is not any of'),
      self::CONDITION_INCLUDE_ALL     => pht('include all of'),
      self::CONDITION_INCLUDE_ANY     => pht('include any of'),
      self::CONDITION_INCLUDE_NONE    => pht('include none of'),
      self::CONDITION_IS_ME           => pht('is myself'),
      self::CONDITION_IS_NOT_ME       => pht('is not myself'),
      self::CONDITION_REGEXP          => pht('matches regexp'),
      self::CONDITION_NOT_REGEXP      => pht('does not match regexp'),
      self::CONDITION_RULE            => pht('matches:'),
      self::CONDITION_NOT_RULE        => pht('does not match:'),
      self::CONDITION_EXISTS          => pht('exists'),
      self::CONDITION_NOT_EXISTS      => pht('does not exist'),
      self::CONDITION_UNCONDITIONALLY => '',  // don't show anything!
      self::CONDITION_NEVER           => '',  // don't show anything!
      self::CONDITION_REGEXP_PAIR     => pht('matches regexp pair'),
      self::CONDITION_HAS_BIT         => pht('has bit'),
      self::CONDITION_NOT_BIT         => pht('lacks bit'),
    );
  }

  public function getConditionsForField($field) {
    return $this->requireFieldImplementation($field)
      ->getHeraldFieldConditions();
  }

  private function requireFieldImplementation($field_key) {
    $field = $this->getFieldImplementation($field_key);

    if (!$field) {
      throw new Exception(
        pht(
          'No field with key "%s" is available to Herald adapter "%s".',
          $field_key,
          get_class($this)));
    }

    return $field;
  }

  public function doesConditionMatch(
    HeraldEngine $engine,
    HeraldRule $rule,
    HeraldCondition $condition,
    $field_value) {

    $condition_type = $condition->getFieldCondition();
    $condition_value = $condition->getValue();

    switch ($condition_type) {
      case self::CONDITION_CONTAINS:
      case self::CONDITION_NOT_CONTAINS:
        // "Contains and "does not contain" can take an array of strings, as in
        // "Any changed filename" for diffs.

        $result_if_match = ($condition_type == self::CONDITION_CONTAINS);

        foreach ((array)$field_value as $value) {
          if (stripos($value, $condition_value) !== false) {
            return $result_if_match;
          }
        }
        return !$result_if_match;
      case self::CONDITION_IS:
        return ($field_value == $condition_value);
      case self::CONDITION_IS_NOT:
        return ($field_value != $condition_value);
      case self::CONDITION_IS_ME:
        return ($field_value == $rule->getAuthorPHID());
      case self::CONDITION_IS_NOT_ME:
        return ($field_value != $rule->getAuthorPHID());
      case self::CONDITION_IS_ANY:
        if (!is_array($condition_value)) {
          throw new HeraldInvalidConditionException(
            pht('Expected condition value to be an array.'));
        }
        $condition_value = array_fuse($condition_value);
        return isset($condition_value[$field_value]);
      case self::CONDITION_IS_NOT_ANY:
        if (!is_array($condition_value)) {
          throw new HeraldInvalidConditionException(
            pht('Expected condition value to be an array.'));
        }
        $condition_value = array_fuse($condition_value);
        return !isset($condition_value[$field_value]);
      case self::CONDITION_INCLUDE_ALL:
        if (!is_array($field_value)) {
          throw new HeraldInvalidConditionException(
            pht('Object produced non-array value!'));
        }
        if (!is_array($condition_value)) {
          throw new HeraldInvalidConditionException(
            pht('Expected condition value to be an array.'));
        }

        $have = array_select_keys(array_fuse($field_value), $condition_value);
        return (count($have) == count($condition_value));
      case self::CONDITION_INCLUDE_ANY:
        return (bool)array_select_keys(
          array_fuse($field_value),
          $condition_value);
      case self::CONDITION_INCLUDE_NONE:
        return !array_select_keys(
          array_fuse($field_value),
          $condition_value);
      case self::CONDITION_EXISTS:
      case self::CONDITION_IS_TRUE:
        return (bool)$field_value;
      case self::CONDITION_NOT_EXISTS:
      case self::CONDITION_IS_FALSE:
        return !$field_value;
      case self::CONDITION_UNCONDITIONALLY:
        return (bool)$field_value;
      case self::CONDITION_NEVER:
        return false;
      case self::CONDITION_REGEXP:
      case self::CONDITION_NOT_REGEXP:
        $result_if_match = ($condition_type == self::CONDITION_REGEXP);

        // We add the 'S' flag because we use the regexp multiple times.
        // It shouldn't cause any troubles if the flag is already there
        // - /.*/S is evaluated same as /.*/SS.
        $condition_pattern = $condition_value.'S';

        foreach ((array)$field_value as $value) {
          try {
            $result = phutil_preg_match($condition_pattern, $value);
          } catch (PhutilRegexException $ex) {
            $message = array();
            $message[] = pht(
              'Regular expression "%s" in Herald rule "%s" is not valid, '.
              'or exceeded backtracking or recursion limits while '.
              'executing. Verify the expression and correct it or rewrite '.
              'it with less backtracking.',
              $condition_value,
              $rule->getMonogram());
            $message[] = $ex->getMessage();
            $message = implode("\n\n", $message);

            throw new HeraldInvalidConditionException($message);
          }

          if ($result) {
            return $result_if_match;
          }
        }
        return !$result_if_match;
      case self::CONDITION_REGEXP_PAIR:
        // Match a JSON-encoded pair of regular expressions against a
        // dictionary. The first regexp must match the dictionary key, and the
        // second regexp must match the dictionary value. If any key/value pair
        // in the dictionary matches both regexps, the condition is satisfied.
        $regexp_pair = null;
        try {
          $regexp_pair = phutil_json_decode($condition_value);
        } catch (PhutilJSONParserException $ex) {
          throw new HeraldInvalidConditionException(
            pht('Regular expression pair is not valid JSON!'));
        }
        if (count($regexp_pair) != 2) {
          throw new HeraldInvalidConditionException(
            pht('Regular expression pair is not a pair!'));
        }

        $key_regexp   = array_shift($regexp_pair);
        $value_regexp = array_shift($regexp_pair);

        foreach ((array)$field_value as $key => $value) {
          $key_matches = @preg_match($key_regexp, $key);
          if ($key_matches === false) {
            throw new HeraldInvalidConditionException(
              pht('First regular expression is invalid!'));
          }
          if ($key_matches) {
            $value_matches = @preg_match($value_regexp, $value);
            if ($value_matches === false) {
              throw new HeraldInvalidConditionException(
                pht('Second regular expression is invalid!'));
            }
            if ($value_matches) {
              return true;
            }
          }
        }
        return false;
      case self::CONDITION_RULE:
      case self::CONDITION_NOT_RULE:
        $rule = $engine->getRule($condition_value);
        if (!$rule) {
          throw new HeraldInvalidConditionException(
            pht('Condition references a rule which does not exist!'));
        }

        $is_not = ($condition_type == self::CONDITION_NOT_RULE);
        $result = $engine->doesRuleMatch($rule, $this);
        if ($is_not) {
          $result = !$result;
        }
        return $result;
      case self::CONDITION_HAS_BIT:
        return (($condition_value & $field_value) === (int)$condition_value);
      case self::CONDITION_NOT_BIT:
        return (($condition_value & $field_value) !== (int)$condition_value);
      default:
        throw new HeraldInvalidConditionException(
          pht("Unknown condition '%s'.", $condition_type));
    }
  }

  public function willSaveCondition(HeraldCondition $condition) {
    $condition_type = $condition->getFieldCondition();
    $condition_value = $condition->getValue();

    switch ($condition_type) {
      case self::CONDITION_REGEXP:
      case self::CONDITION_NOT_REGEXP:
        $ok = @preg_match($condition_value, '');
        if ($ok === false) {
          throw new HeraldInvalidConditionException(
            pht(
              'The regular expression "%s" is not valid. Regular expressions '.
              'must have enclosing characters (e.g. "@/path/to/file@", not '.
              '"/path/to/file") and be syntactically correct.',
              $condition_value));
        }
        break;
      case self::CONDITION_REGEXP_PAIR:
        $json = null;
        try {
          $json = phutil_json_decode($condition_value);
        } catch (PhutilJSONParserException $ex) {
          throw new HeraldInvalidConditionException(
            pht(
              'The regular expression pair "%s" is not valid JSON. Enter a '.
              'valid JSON array with two elements.',
              $condition_value));
        }

        if (count($json) != 2) {
          throw new HeraldInvalidConditionException(
            pht(
              'The regular expression pair "%s" must have exactly two '.
              'elements.',
              $condition_value));
        }

        $key_regexp = array_shift($json);
        $val_regexp = array_shift($json);

        $key_ok = @preg_match($key_regexp, '');
        if ($key_ok === false) {
          throw new HeraldInvalidConditionException(
            pht(
              'The first regexp in the regexp pair, "%s", is not a valid '.
              'regexp.',
              $key_regexp));
        }

        $val_ok = @preg_match($val_regexp, '');
        if ($val_ok === false) {
          throw new HeraldInvalidConditionException(
            pht(
              'The second regexp in the regexp pair, "%s", is not a valid '.
              'regexp.',
              $val_regexp));
        }
        break;
      case self::CONDITION_CONTAINS:
      case self::CONDITION_NOT_CONTAINS:
      case self::CONDITION_IS:
      case self::CONDITION_IS_NOT:
      case self::CONDITION_IS_ANY:
      case self::CONDITION_IS_NOT_ANY:
      case self::CONDITION_INCLUDE_ALL:
      case self::CONDITION_INCLUDE_ANY:
      case self::CONDITION_INCLUDE_NONE:
      case self::CONDITION_IS_ME:
      case self::CONDITION_IS_NOT_ME:
      case self::CONDITION_RULE:
      case self::CONDITION_NOT_RULE:
      case self::CONDITION_EXISTS:
      case self::CONDITION_NOT_EXISTS:
      case self::CONDITION_UNCONDITIONALLY:
      case self::CONDITION_NEVER:
      case self::CONDITION_HAS_BIT:
      case self::CONDITION_NOT_BIT:
      case self::CONDITION_IS_TRUE:
      case self::CONDITION_IS_FALSE:
        // No explicit validation for these types, although there probably
        // should be in some cases.
        break;
      default:
        throw new HeraldInvalidConditionException(
          pht(
            'Unknown condition "%s"!',
            $condition_type));
    }
  }


/* -(  Actions  )------------------------------------------------------------ */

  private function getActionImplementationMap() {
    if ($this->actionMap === null) {
      // We can't use PhutilClassMapQuery here because action expansion
      // depends on the adapter and object.

      $object = $this->getObject();

      $map = array();
      $all = HeraldAction::getAllActions();
      foreach ($all as $key => $action) {
        $action = id(clone $action)->setAdapter($this);

        if (!$action->supportsObject($object)) {
          continue;
        }

        $subactions = $action->getActionsForObject($object);
        foreach ($subactions as $subkey => $subaction) {
          if (isset($map[$subkey])) {
            throw new Exception(
              pht(
                'Two HeraldActions (of classes "%s" and "%s") have the same '.
                'action key ("%s") after expansion for an object of class '.
                '"%s" inside adapter "%s". Each action must have a unique '.
                'action key.',
                get_class($subaction),
                get_class($map[$subkey]),
                $subkey,
                get_class($object),
                get_class($this)));
          }

          $subaction = id(clone $subaction)->setAdapter($this);

          $map[$subkey] = $subaction;
        }
      }
      $this->actionMap = $map;
    }

    return $this->actionMap;
  }

  private function requireActionImplementation($action_key) {
    $action = $this->getActionImplementation($action_key);

    if (!$action) {
      throw new Exception(
        pht(
          'No action with key "%s" is available to Herald adapter "%s".',
          $action_key,
          get_class($this)));
    }

    return $action;
  }

  private function getActionsForRuleType($rule_type) {
    $actions = $this->getActionImplementationMap();

    foreach ($actions as $key => $action) {
      if (!$action->supportsRuleType($rule_type)) {
        unset($actions[$key]);
      }
    }

    return $actions;
  }

  public function getActionImplementation($key) {
    return idx($this->getActionImplementationMap(), $key);
  }

  public function getActionKeys() {
    return array_keys($this->getActionImplementationMap());
  }

  public function getActionGroupKey($action_key) {
    $action = $this->getActionImplementation($action_key);
    if (!$action) {
      return null;
    }

    return $action->getActionGroupKey();
  }

  public function isActionAvailable($action_key) {
    $action = $this->getActionImplementation($action_key);

    if (!$action) {
      return null;
    }

    return $action->isActionAvailable();
  }

  public function getActions($rule_type) {
    $actions = array();
    foreach ($this->getActionsForRuleType($rule_type) as $key => $action) {
      $actions[] = $key;
    }

    return $actions;
  }

  public function getActionNameMap($rule_type) {
    $map = array();
    foreach ($this->getActionsForRuleType($rule_type) as $key => $action) {
      $map[$key] = $action->getHeraldActionName();
    }

    return $map;
  }

  public function willSaveAction(
    HeraldRule $rule,
    HeraldActionRecord $action) {

    $impl = $this->requireActionImplementation($action->getAction());
    $target = $action->getTarget();
    $target = $impl->willSaveActionValue($target);

    $action->setTarget($target);
  }



/* -(  Values  )------------------------------------------------------------- */


  public function getValueTypeForFieldAndCondition($field, $condition) {
    return $this->requireFieldImplementation($field)
      ->getHeraldFieldValueType($condition);
  }

  public function getValueTypeForAction($action, $rule_type) {
    $impl = $this->requireActionImplementation($action);
    return $impl->getHeraldActionValueType();
  }

  private function buildTokenizerFieldValue(
    PhabricatorTypeaheadDatasource $datasource) {

    $key = 'action.'.get_class($datasource);

    return id(new HeraldTokenizerFieldValue())
      ->setKey($key)
      ->setDatasource($datasource);
  }

/* -(  Repetition  )--------------------------------------------------------- */


  public function getRepetitionOptions() {
    $options = array();

    $options[] = HeraldRule::REPEAT_EVERY;

    // Some rules, like pre-commit rules, only ever fire once. It doesn't
    // make sense to use state-based repetition policies like "only the first
    // time" for these rules.

    if (!$this->isSingleEventAdapter()) {
      $options[] = HeraldRule::REPEAT_FIRST;
      $options[] = HeraldRule::REPEAT_CHANGE;
    }

    return $options;
  }

  protected function initializeNewAdapter() {
    $this->setObject($this->newObject());
    return $this;
  }

  /**
   * Does this adapter's event fire only once?
   *
   * Single use adapters (like pre-commit and diff adapters) only fire once,
   * so fields like "Is new object" don't make sense to apply to their content.
   *
   * @return bool
   */
  public function isSingleEventAdapter() {
    return false;
  }

  public static function getAllAdapters() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getAdapterContentType')
      ->setSortMethod('getAdapterSortKey')
      ->execute();
  }

  public static function getAdapterForContentType($content_type) {
    $adapters = self::getAllAdapters();

    foreach ($adapters as $adapter) {
      if ($adapter->getAdapterContentType() == $content_type) {
        $adapter = id(clone $adapter);
        $adapter->initializeNewAdapter();
        return $adapter;
      }
    }

    throw new Exception(
      pht(
        'No adapter exists for Herald content type "%s".',
        $content_type));
  }

  public static function getEnabledAdapterMap(PhabricatorUser $viewer) {
    $map = array();

    $adapters = self::getAllAdapters();
    foreach ($adapters as $adapter) {
      if (!$adapter->isAvailableToUser($viewer)) {
        continue;
      }
      $type = $adapter->getAdapterContentType();
      $name = $adapter->getAdapterContentName();
      $map[$type] = $name;
    }

    return $map;
  }

  public function getEditorValueForCondition(
    PhabricatorUser $viewer,
    HeraldCondition $condition) {

    $field = $this->requireFieldImplementation($condition->getFieldName());

    return $field->getEditorValue(
      $viewer,
      $condition->getFieldCondition(),
      $condition->getValue());
  }

  public function getEditorValueForAction(
    PhabricatorUser $viewer,
    HeraldActionRecord $action_record) {

    $action = $this->requireActionImplementation($action_record->getAction());

    return $action->getEditorValue(
      $viewer,
      $action_record->getTarget());
  }

  public function renderRuleAsText(
    HeraldRule $rule,
    PhabricatorUser $viewer) {

    require_celerity_resource('herald-css');

    $icon = id(new PHUIIconView())
      ->setIcon('fa-chevron-circle-right lightgreytext')
      ->addClass('herald-list-icon');

    if ($rule->getMustMatchAll()) {
      $match_text = pht('When all of these conditions are met:');
    } else {
      $match_text = pht('When any of these conditions are met:');
    }

    $match_title = phutil_tag(
      'p',
      array(
        'class' => 'herald-list-description',
      ),
      $match_text);

    $match_list = array();
    foreach ($rule->getConditions() as $condition) {
      $match_list[] = phutil_tag(
        'div',
        array(
          'class' => 'herald-list-item',
        ),
        array(
          $icon,
          $this->renderConditionAsText($condition, $viewer),
        ));
    }

    if ($rule->isRepeatFirst()) {
      $action_text = pht(
        'Take these actions the first time this rule matches:');
    } else if ($rule->isRepeatOnChange()) {
      $action_text = pht(
        'Take these actions if this rule did not match the last time:');
    } else {
      $action_text = pht(
        'Take these actions every time this rule matches:');
    }

    $action_title = phutil_tag(
      'p',
      array(
        'class' => 'herald-list-description',
      ),
      $action_text);

    $action_list = array();
    foreach ($rule->getActions() as $action) {
      $action_list[] = phutil_tag(
        'div',
        array(
          'class' => 'herald-list-item',
        ),
        array(
          $icon,
          $this->renderActionAsText($viewer, $action),
        ));
    }

    return array(
      $match_title,
      $match_list,
      $action_title,
      $action_list,
    );
  }

  private function renderConditionAsText(
    HeraldCondition $condition,
    PhabricatorUser $viewer) {

    $field_type = $condition->getFieldName();
    $field = $this->getFieldImplementation($field_type);

    if (!$field) {
      return pht('Unknown Field: "%s"', $field_type);
    }

    $field_name = $field->getHeraldFieldName();

    $condition_type = $condition->getFieldCondition();
    $condition_name = idx($this->getConditionNameMap(), $condition_type);

    $value = $this->renderConditionValueAsText($condition, $viewer);

    return array(
      $field_name,
      ' ',
      $condition_name,
      ' ',
      $value,
    );
  }

  private function renderActionAsText(
    PhabricatorUser $viewer,
    HeraldActionRecord $action_record) {

    $action_type = $action_record->getAction();
    $action_value = $action_record->getTarget();

    $action = $this->getActionImplementation($action_type);
    if (!$action) {
      return pht('Unknown Action ("%s")', $action_type);
    }

    $action->setViewer($viewer);

    return $action->renderActionDescription($action_value);
  }

  private function renderConditionValueAsText(
    HeraldCondition $condition,
    PhabricatorUser $viewer) {

    $field = $this->requireFieldImplementation($condition->getFieldName());

    return $field->renderConditionValue(
      $viewer,
      $condition->getFieldCondition(),
      $condition->getValue());
  }

  public function renderFieldTranscriptValue(
    PhabricatorUser $viewer,
    $field_type,
    $field_value) {

    $field = $this->getFieldImplementation($field_type);
    if ($field) {
      return $field->renderTranscriptValue(
        $viewer,
        $field_value);
    }

    return phutil_tag(
      'em',
      array(),
      pht(
        'Unable to render value for unknown field type ("%s").',
        $field_type));
  }


/* -(  Applying Effects  )--------------------------------------------------- */


  /**
   * @task apply
   */
  protected function applyStandardEffect(HeraldEffect $effect) {
    $action = $effect->getAction();
    $rule_type = $effect->getRule()->getRuleType();

    $impl = $this->getActionImplementation($action);
    if (!$impl) {
      return new HeraldApplyTranscript(
        $effect,
        false,
        array(
          array(
            HeraldAction::DO_STANDARD_INVALID_ACTION,
            $action,
          ),
        ));
    }

    if (!$impl->supportsRuleType($rule_type)) {
      return new HeraldApplyTranscript(
        $effect,
        false,
        array(
          array(
            HeraldAction::DO_STANDARD_WRONG_RULE_TYPE,
            $rule_type,
          ),
        ));
    }

    $impl->applyEffect($this->getObject(), $effect);
    return $impl->getApplyTranscript($effect);
  }

  public function loadEdgePHIDs($type) {
    if (!isset($this->edgeCache[$type])) {
      $phids = PhabricatorEdgeQuery::loadDestinationPHIDs(
        $this->getObject()->getPHID(),
        $type);

      $this->edgeCache[$type] = array_fuse($phids);
    }
    return $this->edgeCache[$type];
  }


/* -(  Forbidden Actions  )-------------------------------------------------- */


  final public function getForbiddenActions() {
    return array_keys($this->forbiddenActions);
  }

  final public function setForbiddenAction($action, $reason) {
    $this->forbiddenActions[$action] = $reason;
    return $this;
  }

  final public function getRequiredFieldStates($field_key) {
    return $this->requireFieldImplementation($field_key)
      ->getRequiredAdapterStates();
  }

  final public function getRequiredActionStates($action_key) {
    return $this->requireActionImplementation($action_key)
      ->getRequiredAdapterStates();
  }

  final public function getForbiddenReason($action) {
    if (!isset($this->forbiddenActions[$action])) {
      throw new Exception(
        pht(
          'Action "%s" is not forbidden!',
          $action));
    }

    return $this->forbiddenActions[$action];
  }


/* -(  Must Encrypt  )------------------------------------------------------- */


  final public function addMustEncryptReason($reason) {
    $this->mustEncryptReasons[] = $reason;
    return $this;
  }

  final public function getMustEncryptReasons() {
    return $this->mustEncryptReasons;
  }


/* -(  Webhooks  )----------------------------------------------------------- */


  public function supportsWebhooks() {
    return true;
  }


  final public function queueWebhook($webhook_phid, $rule_phid) {
    $this->webhookMap[$webhook_phid][] = $rule_phid;
    return $this;
  }

  final public function getWebhookMap() {
    return $this->webhookMap;
  }

}
