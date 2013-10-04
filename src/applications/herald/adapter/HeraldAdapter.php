<?php

/**
 * @group herald
 */
abstract class HeraldAdapter {

  const FIELD_TITLE                  = 'title';
  const FIELD_BODY                   = 'body';
  const FIELD_AUTHOR                 = 'author';
  const FIELD_REVIEWER               = 'reviewer';
  const FIELD_REVIEWERS              = 'reviewers';
  const FIELD_COMMITTER              = 'committer';
  const FIELD_CC                     = 'cc';
  const FIELD_TAGS                   = 'tags';
  const FIELD_DIFF_FILE              = 'diff-file';
  const FIELD_DIFF_CONTENT           = 'diff-content';
  const FIELD_DIFF_ADDED_CONTENT     = 'diff-added-content';
  const FIELD_DIFF_REMOVED_CONTENT   = 'diff-removed-content';
  const FIELD_REPOSITORY             = 'repository';
  const FIELD_RULE                   = 'rule';
  const FIELD_AFFECTED_PACKAGE       = 'affected-package';
  const FIELD_AFFECTED_PACKAGE_OWNER = 'affected-package-owner';
  const FIELD_CONTENT_SOURCE         = 'contentsource';
  const FIELD_ALWAYS                 = 'always';

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
  const CONDITION_REGEXP_PAIR     = 'regexp-pair';

  const ACTION_ADD_CC       = 'addcc';
  const ACTION_REMOVE_CC    = 'remcc';
  const ACTION_EMAIL        = 'email';
  const ACTION_NOTHING      = 'nothing';
  const ACTION_AUDIT        = 'audit';
  const ACTION_FLAG         = 'flag';
  const ACTION_ASSIGN_TASK  = 'assigntask';
  const ACTION_ADD_PROJECTS = 'addprojects';

  const VALUE_TEXT            = 'text';
  const VALUE_NONE            = 'none';
  const VALUE_EMAIL           = 'email';
  const VALUE_USER            = 'user';
  const VALUE_TAG             = 'tag';
  const VALUE_RULE            = 'rule';
  const VALUE_REPOSITORY      = 'repository';
  const VALUE_OWNERS_PACKAGE  = 'package';
  const VALUE_PROJECT         = 'project';
  const VALUE_FLAG_COLOR      = 'flagcolor';
  const VALUE_CONTENT_SOURCE  = 'contentsource';

  private $contentSource;

  public function setContentSource(PhabricatorContentSource $content_source) {
    $this->contentSource = $content_source;
    return $this;
  }
  public function getContentSource() {
    return $this->contentSource;
  }

  abstract public function getPHID();
  abstract public function getHeraldName();

  public function getHeraldField($field_name) {
    switch ($field_name) {
      case self::FIELD_RULE:
        return null;
      case self::FIELD_CONTENT_SOURCE:
        return $this->getContentSource()->getSource();
      case self::FIELD_ALWAYS:
        return true;
      default:
        throw new Exception(
          "Unknown field '{$field_name}'!");
    }
  }

  abstract public function applyHeraldEffects(array $effects);

  public function isAvailableToUser(PhabricatorUser $viewer) {
    $applications = id(new PhabricatorApplicationQuery())
      ->setViewer($viewer)
      ->withInstalled(true)
      ->withClasses(array($this->getAdapterApplicationClass()))
      ->execute();

    return !empty($applications);
  }


  /**
   * NOTE: You generally should not override this; it exists to support legacy
   * adapters which had hard-coded content types.
   */
  public function getAdapterContentType() {
    return get_class($this);
  }

  abstract public function getAdapterContentName();
  abstract public function getAdapterApplicationClass();


/* -(  Fields  )------------------------------------------------------------- */


  public function getFields() {
    return array(
      self::FIELD_ALWAYS,
    );
  }

  public function getFieldNameMap() {
    return array(
      self::FIELD_TITLE => pht('Title'),
      self::FIELD_BODY => pht('Body'),
      self::FIELD_AUTHOR => pht('Author'),
      self::FIELD_COMMITTER => pht('Committer'),
      self::FIELD_REVIEWER => pht('Reviewer'),
      self::FIELD_REVIEWERS => pht('Reviewers'),
      self::FIELD_CC => pht('CCs'),
      self::FIELD_TAGS => pht('Tags'),
      self::FIELD_DIFF_FILE => pht('Any changed filename'),
      self::FIELD_DIFF_CONTENT => pht('Any changed file content'),
      self::FIELD_DIFF_ADDED_CONTENT => pht('Any added file content'),
      self::FIELD_DIFF_REMOVED_CONTENT => pht('Any removed file content'),
      self::FIELD_REPOSITORY => pht('Repository'),
      self::FIELD_RULE => pht('Another Herald rule'),
      self::FIELD_AFFECTED_PACKAGE => pht('Any affected package'),
      self::FIELD_AFFECTED_PACKAGE_OWNER =>
        pht("Any affected package's owner"),
      self::FIELD_CONTENT_SOURCE => pht('Content Source'),
      self::FIELD_ALWAYS => pht('Always'),
    );
  }


/* -(  Conditions  )--------------------------------------------------------- */


  public function getConditionNameMap() {
    return array(
      self::CONDITION_CONTAINS        => pht('contains'),
      self::CONDITION_NOT_CONTAINS    => pht('does not contain'),
      self::CONDITION_IS              => pht('is'),
      self::CONDITION_IS_NOT          => pht('is not'),
      self::CONDITION_IS_ANY          => pht('is any of'),
      self::CONDITION_IS_NOT_ANY      => pht('is not any of'),
      self::CONDITION_INCLUDE_ALL     => pht('include all of'),
      self::CONDITION_INCLUDE_ANY     => pht('include any of'),
      self::CONDITION_INCLUDE_NONE    => pht('include none of'),
      self::CONDITION_IS_ME           => pht('is myself'),
      self::CONDITION_IS_NOT_ME       => pht('is not myself'),
      self::CONDITION_REGEXP          => pht('matches regexp'),
      self::CONDITION_RULE            => pht('matches:'),
      self::CONDITION_NOT_RULE        => pht('does not match:'),
      self::CONDITION_EXISTS          => pht('exists'),
      self::CONDITION_NOT_EXISTS      => pht('does not exist'),
      self::CONDITION_UNCONDITIONALLY => '',  // don't show anything!
      self::CONDITION_REGEXP_PAIR     => pht('matches regexp pair'),
    );
  }

  public function getConditionsForField($field) {
    switch ($field) {
      case self::FIELD_TITLE:
      case self::FIELD_BODY:
        return array(
          self::CONDITION_CONTAINS,
          self::CONDITION_NOT_CONTAINS,
          self::CONDITION_IS,
          self::CONDITION_IS_NOT,
          self::CONDITION_REGEXP,
        );
      case self::FIELD_AUTHOR:
      case self::FIELD_COMMITTER:
      case self::FIELD_REPOSITORY:
      case self::FIELD_REVIEWER:
        return array(
          self::CONDITION_IS_ANY,
          self::CONDITION_IS_NOT_ANY,
        );
      case self::FIELD_TAGS:
      case self::FIELD_REVIEWERS:
      case self::FIELD_CC:
        return array(
          self::CONDITION_INCLUDE_ALL,
          self::CONDITION_INCLUDE_ANY,
          self::CONDITION_INCLUDE_NONE,
        );
      case self::FIELD_DIFF_FILE:
        return array(
          self::CONDITION_CONTAINS,
          self::CONDITION_REGEXP,
        );
      case self::FIELD_DIFF_CONTENT:
      case self::FIELD_DIFF_ADDED_CONTENT:
      case self::FIELD_DIFF_REMOVED_CONTENT:
        return array(
          self::CONDITION_CONTAINS,
          self::CONDITION_REGEXP,
          self::CONDITION_REGEXP_PAIR,
        );
      case self::FIELD_RULE:
        return array(
          self::CONDITION_RULE,
          self::CONDITION_NOT_RULE,
        );
      case self::FIELD_AFFECTED_PACKAGE:
      case self::FIELD_AFFECTED_PACKAGE_OWNER:
        return array(
          self::CONDITION_INCLUDE_ANY,
          self::CONDITION_INCLUDE_NONE,
        );
      case self::FIELD_CONTENT_SOURCE:
        return array(
          self::CONDITION_IS,
          self::CONDITION_IS_NOT,
        );
      case self::FIELD_ALWAYS:
        return array(
          self::CONDITION_UNCONDITIONALLY,
        );
      default:
        throw new Exception(
          "This adapter does not define conditions for field '{$field}'!");
    }
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
            "Expected condition value to be an array.");
        }
        $condition_value = array_fuse($condition_value);
        return isset($condition_value[$field_value]);
      case self::CONDITION_IS_NOT_ANY:
        if (!is_array($condition_value)) {
          throw new HeraldInvalidConditionException(
            "Expected condition value to be an array.");
        }
        $condition_value = array_fuse($condition_value);
        return !isset($condition_value[$field_value]);
      case self::CONDITION_INCLUDE_ALL:
        if (!is_array($field_value)) {
          throw new HeraldInvalidConditionException(
            "Object produced non-array value!");
        }
        if (!is_array($condition_value)) {
          throw new HeraldInvalidConditionException(
            "Expected conditionv value to be an array.");
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
        return (bool)$field_value;
      case self::CONDITION_NOT_EXISTS:
        return !$field_value;
      case self::CONDITION_UNCONDITIONALLY:
        return (bool)$field_value;
      case self::CONDITION_REGEXP:
        foreach ((array)$field_value as $value) {
          // We add the 'S' flag because we use the regexp multiple times.
          // It shouldn't cause any troubles if the flag is already there
          // - /.*/S is evaluated same as /.*/SS.
          $result = @preg_match($condition_value . 'S', $value);
          if ($result === false) {
            throw new HeraldInvalidConditionException(
              "Regular expression is not valid!");
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
        $regexp_pair = json_decode($condition_value, true);
        if (!is_array($regexp_pair)) {
          throw new HeraldInvalidConditionException(
            "Regular expression pair is not valid JSON!");
        }
        if (count($regexp_pair) != 2) {
          throw new HeraldInvalidConditionException(
            "Regular expression pair is not a pair!");
        }

        $key_regexp   = array_shift($regexp_pair);
        $value_regexp = array_shift($regexp_pair);

        foreach ((array)$field_value as $key => $value) {
          $key_matches = @preg_match($key_regexp, $key);
          if ($key_matches === false) {
            throw new HeraldInvalidConditionException(
              "First regular expression is invalid!");
          }
          if ($key_matches) {
            $value_matches = @preg_match($value_regexp, $value);
            if ($value_matches === false) {
              throw new HeraldInvalidConditionException(
                "Second regular expression is invalid!");
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
            "Condition references a rule which does not exist!");
        }

        $is_not = ($condition_type == self::CONDITION_NOT_RULE);
        $result = $engine->doesRuleMatch($rule, $this);
        if ($is_not) {
          $result = !$result;
        }
        return $result;
      default:
        throw new HeraldInvalidConditionException(
          "Unknown condition '{$condition_type}'.");
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
        $json = json_decode($condition_value, true);
        if (!is_array($json)) {
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

  abstract public function getActions($rule_type);

  public function getActionNameMap($rule_type) {
    switch ($rule_type) {
      case HeraldRuleTypeConfig::RULE_TYPE_GLOBAL:
        return array(
          self::ACTION_NOTHING      => pht('Do nothing'),
          self::ACTION_ADD_CC       => pht('Add emails to CC'),
          self::ACTION_REMOVE_CC    => pht('Remove emails from CC'),
          self::ACTION_EMAIL        => pht('Send an email to'),
          self::ACTION_AUDIT        => pht('Trigger an Audit by'),
          self::ACTION_FLAG         => pht('Mark with flag'),
          self::ACTION_ASSIGN_TASK  => pht('Assign task to'),
          self::ACTION_ADD_PROJECTS => pht('Add projects'),
        );
      case HeraldRuleTypeConfig::RULE_TYPE_PERSONAL:
        return array(
          self::ACTION_NOTHING      => pht('Do nothing'),
          self::ACTION_ADD_CC       => pht('Add me to CC'),
          self::ACTION_REMOVE_CC    => pht('Remove me from CC'),
          self::ACTION_EMAIL        => pht('Send me an email'),
          self::ACTION_AUDIT        => pht('Trigger an Audit by me'),
          self::ACTION_FLAG         => pht('Mark with flag'),
          self::ACTION_ASSIGN_TASK  => pht('Assign task to me.'),
          self::ACTION_ADD_PROJECTS => pht('Add projects'),
        );
      default:
        throw new Exception("Unknown rule type '{$rule_type}'!");
    }
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
    switch ($condition) {
      case self::CONDITION_CONTAINS:
      case self::CONDITION_NOT_CONTAINS:
      case self::CONDITION_REGEXP:
      case self::CONDITION_REGEXP_PAIR:
        return self::VALUE_TEXT;
      case self::CONDITION_IS:
      case self::CONDITION_IS_NOT:
        switch ($field) {
          case self::FIELD_CONTENT_SOURCE:
            return self::VALUE_CONTENT_SOURCE;
          default:
            return self::VALUE_TEXT;
        }
        break;
      case self::CONDITION_IS_ANY:
      case self::CONDITION_IS_NOT_ANY:
        switch ($field) {
          case self::FIELD_REPOSITORY:
            return self::VALUE_REPOSITORY;
          default:
            return self::VALUE_USER;
        }
        break;
      case self::CONDITION_INCLUDE_ALL:
      case self::CONDITION_INCLUDE_ANY:
      case self::CONDITION_INCLUDE_NONE:
        switch ($field) {
          case self::FIELD_REPOSITORY:
            return self::VALUE_REPOSITORY;
          case self::FIELD_CC:
            return self::VALUE_EMAIL;
          case self::FIELD_TAGS:
            return self::VALUE_TAG;
          case self::FIELD_AFFECTED_PACKAGE:
            return self::VALUE_OWNERS_PACKAGE;
          default:
            return self::VALUE_USER;
        }
        break;
      case self::CONDITION_IS_ME:
      case self::CONDITION_IS_NOT_ME:
      case self::CONDITION_EXISTS:
      case self::CONDITION_NOT_EXISTS:
      case self::CONDITION_UNCONDITIONALLY:
        return self::VALUE_NONE;
      case self::CONDITION_RULE:
      case self::CONDITION_NOT_RULE:
        return self::VALUE_RULE;
      default:
        throw new Exception("Unknown condition '{$condition}'.");
    }
  }

  public static function getValueTypeForAction($action, $rule_type) {
    $is_personal = ($rule_type == HeraldRuleTypeConfig::RULE_TYPE_PERSONAL);

    if ($is_personal) {
      switch ($action) {
        case self::ACTION_ADD_CC:
        case self::ACTION_REMOVE_CC:
        case self::ACTION_EMAIL:
        case self::ACTION_NOTHING:
        case self::ACTION_AUDIT:
        case self::ACTION_ASSIGN_TASK:
          return self::VALUE_NONE;
        case self::ACTION_FLAG:
          return self::VALUE_FLAG_COLOR;
        case self::ACTION_ADD_PROJECTS:
          return self::VALUE_PROJECT;
        default:
          throw new Exception("Unknown or invalid action '{$action}'.");
      }
    } else {
      switch ($action) {
        case self::ACTION_ADD_CC:
        case self::ACTION_REMOVE_CC:
        case self::ACTION_EMAIL:
          return self::VALUE_EMAIL;
        case self::ACTION_NOTHING:
          return self::VALUE_NONE;
        case self::ACTION_AUDIT:
        case self::ACTION_ADD_PROJECTS:
          return self::VALUE_PROJECT;
        case self::ACTION_FLAG:
          return self::VALUE_FLAG_COLOR;
        case self::ACTION_ASSIGN_TASK:
          return self::VALUE_USER;
        default:
          throw new Exception("Unknown or invalid action '{$action}'.");
      }
    }
  }


/* -(  Repetition  )--------------------------------------------------------- */


  public function getRepetitionOptions() {
    return array(
      HeraldRepetitionPolicyConfig::EVERY,
    );
  }


  public static function applyFlagEffect(HeraldEffect $effect, $phid) {
    $color = $effect->getTarget();

    // TODO: Silly that we need to load this again here.
    $rule = id(new HeraldRule())->load($effect->getRuleID());
    $user = id(new PhabricatorUser())->loadOneWhere(
      'phid = %s',
      $rule->getAuthorPHID());

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

  public static function getAllAdapters() {
    static $adapters;
    if (!$adapters) {
      $adapters = id(new PhutilSymbolLoader())
        ->setAncestorClass(__CLASS__)
        ->loadObjects();
    }
    return $adapters;
  }

  public static function getAdapterForContentType($content_type) {
    $adapters = self::getAllAdapters();

    foreach ($adapters as $adapter) {
      if ($adapter->getAdapterContentType() == $content_type) {
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

    $adapters = HeraldAdapter::getAllAdapters();
    foreach ($adapters as $adapter) {
      if (!$adapter->isAvailableToUser($viewer)) {
        continue;
      }
      $type = $adapter->getAdapterContentType();
      $name = $adapter->getAdapterContentName();
      $map[$type] = $name;
    }

    asort($map);
    return $map;
  }

  public function renderRuleAsText(HeraldRule $rule, array $handles) {
    assert_instances_of($handles, 'PhabricatorObjectHandle');

    $out = array();

    if ($rule->getMustMatchAll()) {
      $out[] = pht('When all of these conditions are met:');
    } else {
      $out[] = pht('When any of these conditions are met:');
    }

    $out[] = null;
    foreach ($rule->getConditions() as $condition) {
      $out[] = $this->renderConditionAsText($condition, $handles);
    }
    $out[] = null;

    if ($rule->getRepetitionPolicy() == HeraldRepetitionPolicyConfig::EVERY) {
      $out[] = pht('Take these actions every time this rule matches:');
    } else {
      $out[] = pht('Take these actions the first time this rule matches:');
    }

    $out[] = null;
    foreach ($rule->getActions() as $action) {
      $out[] = $this->renderActionAsText($action, $handles);
    }

    return phutil_implode_html("\n", $out);
  }

  private function renderConditionAsText(
    HeraldCondition $condition,
    array $handles) {
    $field_type = $condition->getFieldName();
    $field_name = idx($this->getFieldNameMap(), $field_type);

    $condition_type = $condition->getFieldCondition();
    $condition_name = idx($this->getConditionNameMap(), $condition_type);

    $value = $this->renderConditionValueAsText($condition, $handles);

    return hsprintf('    %s %s %s', $field_name, $condition_name, $value);
  }

  private function renderActionAsText(
    HeraldAction $action,
    array $handles) {
    $rule_global = HeraldRuleTypeConfig::RULE_TYPE_GLOBAL;

    $action_type = $action->getAction();
    $action_name = idx($this->getActionNameMap($rule_global), $action_type);

    $target = $this->renderActionTargetAsText($action, $handles);

    return hsprintf('    %s %s', $action_name, $target);
  }

  private function renderConditionValueAsText(
    HeraldCondition $condition,
    array $handles) {

    $value = $condition->getValue();
    if (!is_array($value)) {
      $value = array($value);
    }
    foreach ($value as $index => $val) {
      $handle = idx($handles, $val);
      if ($handle) {
        $value[$index] = $handle->renderLink();
      }
    }
    $value = phutil_implode_html(', ', $value);
    return $value;
  }

  private function renderActionTargetAsText(
    HeraldAction $action,
    array $handles) {

    $target = $action->getTarget();
    if (!is_array($target)) {
      $target = array($target);
    }
    foreach ($target as $index => $val) {
      $handle = idx($handles, $val);
      if ($handle) {
        $target[$index] = $handle->renderLink();
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
    return $phids;
  }

}

