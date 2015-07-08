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

  const ACTION_ADD_CC       = 'addcc';
  const ACTION_REMOVE_CC    = 'remcc';
  const ACTION_EMAIL        = 'email';
  const ACTION_NOTHING      = 'nothing';
  const ACTION_AUDIT        = 'audit';
  const ACTION_FLAG         = 'flag';
  const ACTION_ASSIGN_TASK  = 'assigntask';
  const ACTION_ADD_PROJECTS = 'addprojects';
  const ACTION_REMOVE_PROJECTS = 'removeprojects';
  const ACTION_ADD_REVIEWERS = 'addreviewers';
  const ACTION_ADD_BLOCKING_REVIEWERS = 'addblockingreviewers';
  const ACTION_APPLY_BUILD_PLANS = 'applybuildplans';
  const ACTION_BLOCK = 'block';
  const ACTION_REQUIRE_SIGNATURE = 'signature';

  const VALUE_TEXT            = 'text';
  const VALUE_NONE            = 'none';
  const VALUE_EMAIL           = 'email';
  const VALUE_USER            = 'user';
  const VALUE_RULE            = 'rule';
  const VALUE_REPOSITORY      = 'repository';
  const VALUE_OWNERS_PACKAGE  = 'package';
  const VALUE_PROJECT         = 'project';
  const VALUE_FLAG_COLOR      = 'flagcolor';
  const VALUE_CONTENT_SOURCE  = 'contentsource';
  const VALUE_USER_OR_PROJECT = 'userorproject';
  const VALUE_BUILD_PLAN      = 'buildplan';
  const VALUE_TASK_PRIORITY   = 'taskpriority';
  const VALUE_TASK_STATUS     = 'taskstatus';
  const VALUE_LEGAL_DOCUMENTS   = 'legaldocuments';
  const VALUE_APPLICATION_EMAIL = 'applicationemail';
  const VALUE_SPACE = 'space';

  private $contentSource;
  private $isNewObject;
  private $applicationEmail;
  private $customActions = null;
  private $queuedTransactions = array();
  private $emailPHIDs = array();
  private $forcedEmailPHIDs = array();
  private $unsubscribedPHIDs;
  private $fieldMap;

  public function getEmailPHIDs() {
    return array_values($this->emailPHIDs);
  }

  public function getForcedEmailPHIDs() {
    return array_values($this->forcedEmailPHIDs);
  }

  public function getCustomActions() {
    if ($this->customActions === null) {
      $custom_actions = id(new PhutilSymbolLoader())
        ->setAncestorClass('HeraldCustomAction')
        ->loadObjects();

      foreach ($custom_actions as $key => $object) {
        if (!$object->appliesToAdapter($this)) {
          unset($custom_actions[$key]);
        }
      }

      $this->customActions = array();
      foreach ($custom_actions as $action) {
        $key = $action->getActionKey();

        if (array_key_exists($key, $this->customActions)) {
          throw new Exception(
            pht(
              "More than one Herald custom action implementation ".
              "handles the action key: '%s'.",
              $key));
        }

        $this->customActions[$key] = $action;
      }
    }

    return $this->customActions;
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

  protected function handleCustomHeraldEffect(HeraldEffect $effect) {
    $custom_action = idx($this->getCustomActions(), $effect->getAction());

    if ($custom_action !== null) {
      return $custom_action->applyEffect(
        $this,
        $this->getObject(),
        $effect);
    }

    return null;
  }

  public function isAvailableToUser(PhabricatorUser $viewer) {
    $applications = id(new PhabricatorApplicationQuery())
      ->setViewer($viewer)
      ->withInstalled(true)
      ->withClasses(array($this->getAdapterApplicationClass()))
      ->execute();

    return !empty($applications);
  }

  public function queueTransaction($transaction) {
    $this->queuedTransactions[] = $transaction;
  }

  public function getQueuedTransactions() {
    return $this->queuedTransactions;
  }

  protected function newTransaction() {
    $object = $this->newObject();

    if (!($object instanceof PhabricatorApplicationTransactionInterface)) {
      throw new Exception(
        pht(
          'Unable to build a new transaction for adapter object; it does '.
          'not implement "%s".',
          'PhabricatorApplicationTransactionInterface'));
    }

    return $object->getApplicationTransactionTemplate();
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
      self::CONDITION_INCLUDE_NONE    => pht('do not include'),
      self::CONDITION_IS_ME           => pht('is myself'),
      self::CONDITION_IS_NOT_ME       => pht('is not myself'),
      self::CONDITION_REGEXP          => pht('matches regexp'),
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
        // "Contains" can take an array of strings, as in "Any changed
        // filename" for diffs.
        foreach ((array)$field_value as $value) {
          if (stripos($value, $condition_value) !== false) {
            return true;
          }
        }
        return false;
      case self::CONDITION_NOT_CONTAINS:
        return (stripos($field_value, $condition_value) === false);
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
        foreach ((array)$field_value as $value) {
          // We add the 'S' flag because we use the regexp multiple times.
          // It shouldn't cause any troubles if the flag is already there
          // - /.*/S is evaluated same as /.*/SS.
          $result = @preg_match($condition_value.'S', $value);
          if ($result === false) {
            throw new HeraldInvalidConditionException(
              pht('Regular expression is not valid!'));
          }
          if ($result) {
            return true;
          }
        }
        return false;
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

  public function getCustomActionsForRuleType($rule_type) {
    $results = array();
    foreach ($this->getCustomActions() as $custom_action) {
      if ($custom_action->appliesToRuleType($rule_type)) {
        $results[] = $custom_action;
      }
    }
    return $results;
  }

  public function getActions($rule_type) {
    $custom_actions = $this->getCustomActionsForRuleType($rule_type);
    $custom_actions = mpull($custom_actions, 'getActionKey');

    $actions = $custom_actions;

    $object = $this->newObject();

    if (($object instanceof PhabricatorProjectInterface)) {
      if ($rule_type == HeraldRuleTypeConfig::RULE_TYPE_GLOBAL) {
        $actions[] = self::ACTION_ADD_PROJECTS;
        $actions[] = self::ACTION_REMOVE_PROJECTS;
      }
    }

    return $actions;
  }

  public function getActionNameMap($rule_type) {
    switch ($rule_type) {
      case HeraldRuleTypeConfig::RULE_TYPE_GLOBAL:
      case HeraldRuleTypeConfig::RULE_TYPE_OBJECT:
        $standard = array(
          self::ACTION_NOTHING      => pht('Do nothing'),
          self::ACTION_ADD_CC       => pht('Add Subscribers'),
          self::ACTION_REMOVE_CC    => pht('Remove Subscribers'),
          self::ACTION_EMAIL        => pht('Send an email to'),
          self::ACTION_AUDIT        => pht('Trigger an Audit by'),
          self::ACTION_FLAG         => pht('Mark with flag'),
          self::ACTION_ASSIGN_TASK  => pht('Assign task to'),
          self::ACTION_ADD_PROJECTS => pht('Add projects'),
          self::ACTION_REMOVE_PROJECTS => pht('Remove projects'),
          self::ACTION_ADD_REVIEWERS => pht('Add reviewers'),
          self::ACTION_ADD_BLOCKING_REVIEWERS => pht('Add blocking reviewers'),
          self::ACTION_APPLY_BUILD_PLANS => pht('Run build plans'),
          self::ACTION_REQUIRE_SIGNATURE => pht('Require legal signatures'),
          self::ACTION_BLOCK => pht('Block change with message'),
        );
        break;
      case HeraldRuleTypeConfig::RULE_TYPE_PERSONAL:
        $standard = array(
          self::ACTION_NOTHING      => pht('Do nothing'),
          self::ACTION_ADD_CC       => pht('Add me as a subscriber'),
          self::ACTION_REMOVE_CC    => pht('Remove me as a subscriber'),
          self::ACTION_EMAIL        => pht('Send me an email'),
          self::ACTION_AUDIT        => pht('Trigger an Audit by me'),
          self::ACTION_FLAG         => pht('Mark with flag'),
          self::ACTION_ASSIGN_TASK  => pht('Assign task to me'),
          self::ACTION_ADD_REVIEWERS => pht('Add me as a reviewer'),
          self::ACTION_ADD_BLOCKING_REVIEWERS =>
            pht('Add me as a blocking reviewer'),
        );
        break;
      default:
        throw new Exception(pht("Unknown rule type '%s'!", $rule_type));
    }

    $custom_actions = $this->getCustomActionsForRuleType($rule_type);
    $standard += mpull($custom_actions, 'getActionName', 'getActionKey');

    return $standard;
  }

  public function willSaveAction(
    HeraldRule $rule,
    HeraldAction $action) {

    $target = $action->getTarget();
    if (is_array($target)) {
      $target = array_keys($target);
    }

    $author_phid = $rule->getAuthorPHID();

    $rule_type = $rule->getRuleType();
    if ($rule_type == HeraldRuleTypeConfig::RULE_TYPE_PERSONAL) {
      switch ($action->getAction()) {
        case self::ACTION_EMAIL:
        case self::ACTION_ADD_CC:
        case self::ACTION_REMOVE_CC:
        case self::ACTION_AUDIT:
        case self::ACTION_ASSIGN_TASK:
        case self::ACTION_ADD_REVIEWERS:
        case self::ACTION_ADD_BLOCKING_REVIEWERS:
          // For personal rules, force these actions to target the rule owner.
          $target = array($author_phid);
          break;
        case self::ACTION_FLAG:
          // Make sure flag color is valid; set to blue if not.
          $color_map = PhabricatorFlagColor::getColorNameMap();
          if (empty($color_map[$target])) {
            $target = PhabricatorFlagColor::COLOR_BLUE;
          }
          break;
        case self::ACTION_BLOCK:
        case self::ACTION_NOTHING:
          break;
        default:
          throw new HeraldInvalidActionException(
            pht(
              'Unrecognized action type "%s"!',
              $action->getAction()));
      }
    }

    $action->setTarget($target);
  }



/* -(  Values  )------------------------------------------------------------- */


  public function getValueTypeForFieldAndCondition($field, $condition) {
    return $this->requireFieldImplementation($field)
      ->getHeraldFieldValueType($condition);
  }

  public function getValueTypeForAction($action, $rule_type) {
    $is_personal = ($rule_type == HeraldRuleTypeConfig::RULE_TYPE_PERSONAL);

    if ($is_personal) {
      switch ($action) {
        case self::ACTION_ADD_CC:
        case self::ACTION_REMOVE_CC:
        case self::ACTION_EMAIL:
        case self::ACTION_NOTHING:
        case self::ACTION_AUDIT:
        case self::ACTION_ASSIGN_TASK:
        case self::ACTION_ADD_REVIEWERS:
        case self::ACTION_ADD_BLOCKING_REVIEWERS:
          return self::VALUE_NONE;
        case self::ACTION_FLAG:
          return self::VALUE_FLAG_COLOR;
        case self::ACTION_ADD_PROJECTS:
        case self::ACTION_REMOVE_PROJECTS:
          return self::VALUE_PROJECT;
      }
    } else {
      switch ($action) {
        case self::ACTION_ADD_CC:
        case self::ACTION_REMOVE_CC:
        case self::ACTION_EMAIL:
          return self::VALUE_EMAIL;
        case self::ACTION_NOTHING:
          return self::VALUE_NONE;
        case self::ACTION_ADD_PROJECTS:
        case self::ACTION_REMOVE_PROJECTS:
          return self::VALUE_PROJECT;
        case self::ACTION_FLAG:
          return self::VALUE_FLAG_COLOR;
        case self::ACTION_ASSIGN_TASK:
          return self::VALUE_USER;
        case self::ACTION_AUDIT:
        case self::ACTION_ADD_REVIEWERS:
        case self::ACTION_ADD_BLOCKING_REVIEWERS:
          return self::VALUE_USER_OR_PROJECT;
        case self::ACTION_APPLY_BUILD_PLANS:
          return self::VALUE_BUILD_PLAN;
        case self::ACTION_REQUIRE_SIGNATURE:
          return self::VALUE_LEGAL_DOCUMENTS;
        case self::ACTION_BLOCK:
          return self::VALUE_TEXT;
      }
    }

    $custom_action = idx($this->getCustomActions(), $action);
    if ($custom_action !== null) {
      return $custom_action->getActionType();
    }

    throw new Exception(pht("Unknown or invalid action '%s'.", $action));
  }


/* -(  Repetition  )--------------------------------------------------------- */


  public function getRepetitionOptions() {
    return array(
      HeraldRepetitionPolicyConfig::EVERY,
    );
  }

  abstract protected function initializeNewAdapter();

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
    HeraldCondition $condition,
    array $handles) {

    $impl = $this->getFieldImplementation($condition->getFieldName());
    if ($impl) {
      return $impl->getEditorValue(
        $viewer,
        $condition->getValue());
    }

    $value = $condition->getValue();
    if (is_array($value)) {
      $value_map = array();
      foreach ($value as $k => $phid) {
        $value_map[$phid] = $handles[$phid]->getName();
      }
      $value = $value_map;
    }

    return $value;
  }

  public function renderRuleAsText(
    HeraldRule $rule,
    PhabricatorHandleList $handles,
    PhabricatorUser $viewer) {

    require_celerity_resource('herald-css');

    $icon = id(new PHUIIconView())
      ->setIconFont('fa-chevron-circle-right lightgreytext')
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
          $this->renderConditionAsText($condition, $handles, $viewer),
        ));
    }

    $integer_code_for_every = HeraldRepetitionPolicyConfig::toInt(
      HeraldRepetitionPolicyConfig::EVERY);

    if ($rule->getRepetitionPolicy() == $integer_code_for_every) {
      $action_text =
        pht('Take these actions every time this rule matches:');
    } else {
      $action_text =
        pht('Take these actions the first time this rule matches:');
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
          $this->renderActionAsText($action, $handles),
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
    PhabricatorHandleList $handles,
    PhabricatorUser $viewer) {

    $field_type = $condition->getFieldName();

    $default = pht('(Unknown Field "%s")', $field_type);

    $field_name = idx($this->getFieldNameMap(), $field_type, $default);

    $condition_type = $condition->getFieldCondition();
    $condition_name = idx($this->getConditionNameMap(), $condition_type);

    $value = $this->renderConditionValueAsText($condition, $handles, $viewer);

    return hsprintf('    %s %s %s', $field_name, $condition_name, $value);
  }

  private function renderActionAsText(
    HeraldAction $action,
    PhabricatorHandleList $handles) {
    $rule_global = HeraldRuleTypeConfig::RULE_TYPE_GLOBAL;

    $action_type = $action->getAction();

    $default = pht('(Unknown Action "%s") equals', $action_type);

    $action_name = idx(
      $this->getActionNameMap($rule_global),
      $action_type,
      $default);

    $target = $this->renderActionTargetAsText($action, $handles);

    return hsprintf('    %s %s', $action_name, $target);
  }

  private function renderConditionValueAsText(
    HeraldCondition $condition,
    PhabricatorHandleList $handles,
    PhabricatorUser $viewer) {

    $impl = $this->getFieldImplementation($condition->getFieldName());
    if ($impl) {
      return $impl->renderConditionValue(
        $viewer,
        $condition->getValue());
    }

    $value = $condition->getValue();
    if (!is_array($value)) {
      $value = array($value);
    }

    foreach ($value as $index => $val) {
      $handle = $handles->getHandleIfExists($val);
      if ($handle) {
        $value[$index] = $handle->renderLink();
      }
    }

    $value = phutil_implode_html(', ', $value);
    return $value;
  }

  private function renderActionTargetAsText(
    HeraldAction $action,
    PhabricatorHandleList $handles) {

    $target = $action->getTarget();
    if (!is_array($target)) {
      $target = array($target);
    }
    foreach ($target as $index => $val) {
      switch ($action->getAction()) {
        case self::ACTION_FLAG:
          $target[$index] = PhabricatorFlagColor::getColorName($val);
          break;
        default:
          $handle = $handles->getHandleIfExists($val);
          if ($handle) {
            $target[$index] = $handle->renderLink();
          }
          break;
      }
    }
    $target = phutil_implode_html(', ', $target);
    return $target;
  }

  /**
   * Given a @{class:HeraldRule}, this function extracts all the phids that
   * we'll want to load as handles later.
   *
   * This function performs a somewhat hacky approach to figuring out what
   * is and is not a phid - try to get the phid type and if the type is
   * *not* unknown assume its a valid phid.
   *
   * Don't try this at home. Use more strongly typed data at home.
   *
   * Think of the children.
   */
  public static function getHandlePHIDs(HeraldRule $rule) {
    $phids = array($rule->getAuthorPHID());
    foreach ($rule->getConditions() as $condition) {
      $value = $condition->getValue();
      if (!is_array($value)) {
        $value = array($value);
      }
      foreach ($value as $val) {
        if (phid_get_type($val) !=
            PhabricatorPHIDConstants::PHID_TYPE_UNKNOWN) {
          $phids[] = $val;
        }
      }
    }

    foreach ($rule->getActions() as $action) {
      $target = $action->getTarget();
      if (!is_array($target)) {
        $target = array($target);
      }
      foreach ($target as $val) {
        if (phid_get_type($val) !=
            PhabricatorPHIDConstants::PHID_TYPE_UNKNOWN) {
          $phids[] = $val;
        }
      }
    }

    if ($rule->isObjectRule()) {
      $phids[] = $rule->getTriggerObjectPHID();
    }

    return $phids;
  }

/* -(  Applying Effects  )--------------------------------------------------- */


  /**
   * @task apply
   */
  protected function applyStandardEffect(HeraldEffect $effect) {
    $action = $effect->getAction();

    $rule_type = $effect->getRule()->getRuleType();
    $supported = $this->getActions($rule_type);
    $supported = array_fuse($supported);
    if (empty($supported[$action])) {
      return new HeraldApplyTranscript(
        $effect,
        false,
        pht(
          'Adapter "%s" does not support action "%s" for rule type "%s".',
          get_class($this),
          $action,
          $rule_type));
    }

    switch ($action) {
      case self::ACTION_ADD_PROJECTS:
      case self::ACTION_REMOVE_PROJECTS:
        return $this->applyProjectsEffect($effect);
      case self::ACTION_ADD_CC:
      case self::ACTION_REMOVE_CC:
        return $this->applySubscribersEffect($effect);
      case self::ACTION_FLAG:
        return $this->applyFlagEffect($effect);
      case self::ACTION_EMAIL:
        return $this->applyEmailEffect($effect);
      case self::ACTION_NOTHING:
        return $this->applyNothingEffect($effect);
      default:
        break;
    }

    $result = $this->handleCustomHeraldEffect($effect);

    if (!$result) {
      return new HeraldApplyTranscript(
        $effect,
        false,
        pht(
          'No custom action exists to handle rule action "%s".',
          $action));
    }

    return $result;
  }

  private function applyNothingEffect(HeraldEffect $effect) {
    return new HeraldApplyTranscript(
      $effect,
      true,
      pht('Did nothing.'));
  }

  /**
   * @task apply
   */
  private function applyProjectsEffect(HeraldEffect $effect) {

    if ($effect->getAction() == self::ACTION_ADD_PROJECTS) {
      $kind = '+';
    } else {
      $kind = '-';
    }

    $project_type = PhabricatorProjectObjectHasProjectEdgeType::EDGECONST;
    $project_phids = $effect->getTarget();
    $xaction = $this->newTransaction()
      ->setTransactionType(PhabricatorTransactions::TYPE_EDGE)
      ->setMetadataValue('edge:type', $project_type)
      ->setNewValue(
        array(
          $kind => array_fuse($project_phids),
        ));

    $this->queueTransaction($xaction);

    return new HeraldApplyTranscript(
      $effect,
      true,
      pht('Added projects.'));
  }

  /**
   * @task apply
   */
  private function applySubscribersEffect(HeraldEffect $effect) {
    if ($effect->getAction() == self::ACTION_ADD_CC) {
      $kind = '+';
      $is_add = true;
    } else {
      $kind = '-';
      $is_add = false;
    }

    $subscriber_phids = array_fuse($effect->getTarget());
    if (!$subscriber_phids) {
      return new HeraldApplyTranscript(
        $effect,
        false,
        pht('This action lists no users or objects to affect.'));
    }

    // The "Add Subscribers" rule only adds subscribers who haven't previously
    // unsubscribed from the object explicitly. Filter these subscribers out
    // before continuing.
    $unsubscribed = array();
    if ($is_add) {
      if ($this->unsubscribedPHIDs === null) {
        $this->unsubscribedPHIDs = PhabricatorEdgeQuery::loadDestinationPHIDs(
          $this->getObject()->getPHID(),
          PhabricatorObjectHasUnsubscriberEdgeType::EDGECONST);
      }

      foreach ($this->unsubscribedPHIDs as $phid) {
        if (isset($subscriber_phids[$phid])) {
          $unsubscribed[$phid] = $phid;
          unset($subscriber_phids[$phid]);
        }
      }
    }

    if (!$subscriber_phids) {
      return new HeraldApplyTranscript(
        $effect,
        false,
        pht('All targets have previously unsubscribed explicitly.'));
    }

    // Filter out PHIDs which aren't valid subscribers. Lower levels of the
    // stack will fail loudly if we try to add subscribers with invalid PHIDs
    // or unknown PHID types, so drop them here.
    $invalid = array();
    foreach ($subscriber_phids as $phid) {
      $type = phid_get_type($phid);
      switch ($type) {
        case PhabricatorPeopleUserPHIDType::TYPECONST:
        case PhabricatorProjectProjectPHIDType::TYPECONST:
          break;
        default:
          $invalid[$phid] = $phid;
          unset($subscriber_phids[$phid]);
          break;
      }
    }

    if (!$subscriber_phids) {
      return new HeraldApplyTranscript(
        $effect,
        false,
        pht('All targets are invalid as subscribers.'));
    }

    $xaction = $this->newTransaction()
      ->setTransactionType(PhabricatorTransactions::TYPE_SUBSCRIBERS)
      ->setNewValue(
        array(
          $kind => $subscriber_phids,
        ));

    $this->queueTransaction($xaction);

    // TODO: We could be more detailed about this, but doing it meaningfully
    // probably requires substantial changes to how transactions are rendered
    // first.
    if ($is_add) {
      $message = pht('Subscribed targets.');
    } else {
      $message = pht('Unsubscribed targets.');
    }

    return new HeraldApplyTranscript($effect, true, $message);
  }


  /**
   * @task apply
   */
  private function applyFlagEffect(HeraldEffect $effect) {
    $phid = $this->getPHID();
    $color = $effect->getTarget();

    $rule = $effect->getRule();
    $user = $rule->getAuthor();

    $flag = PhabricatorFlagQuery::loadUserFlag($user, $phid);
    if ($flag) {
      return new HeraldApplyTranscript(
        $effect,
        false,
        pht('Object already flagged.'));
    }

    $handle = id(new PhabricatorHandleQuery())
      ->setViewer($user)
      ->withPHIDs(array($phid))
      ->executeOne();

    $flag = new PhabricatorFlag();
    $flag->setOwnerPHID($user->getPHID());
    $flag->setType($handle->getType());
    $flag->setObjectPHID($handle->getPHID());

    // TOOD: Should really be transcript PHID, but it doesn't exist yet.
    $flag->setReasonPHID($user->getPHID());

    $flag->setColor($color);
    $flag->setNote(
      pht('Flagged by Herald Rule "%s".', $rule->getName()));
    $flag->save();

    return new HeraldApplyTranscript(
      $effect,
      true,
      pht('Added flag.'));
  }


  /**
   * @task apply
   */
  private function applyEmailEffect(HeraldEffect $effect) {
    foreach ($effect->getTarget() as $phid) {
      $this->emailPHIDs[$phid] = $phid;

      // If this is a personal rule, we'll force delivery of a real email. This
      // effect is stronger than notification preferences, so you get an actual
      // email even if your preferences are set to "Notify" or "Ignore".
      $rule = $effect->getRule();
      if ($rule->isPersonalRule()) {
        $this->forcedEmailPHIDs[$phid] = $phid;
      }
    }

    return new HeraldApplyTranscript(
      $effect,
      true,
      pht('Added mailable to mail targets.'));
  }


}
