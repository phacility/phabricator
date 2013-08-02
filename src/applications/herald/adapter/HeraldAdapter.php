<?php

abstract class HeraldAdapter {

  const FIELD_TITLE                  = 'title';
  const FIELD_BODY                   = 'body';
  const FIELD_AUTHOR                 = 'author';
  const FIELD_REVIEWER               = 'reviewer';
  const FIELD_REVIEWERS              = 'reviewers';
  const FIELD_CC                     = 'cc';
  const FIELD_TAGS                   = 'tags';
  const FIELD_DIFF_FILE              = 'diff-file';
  const FIELD_DIFF_CONTENT           = 'diff-content';
  const FIELD_REPOSITORY             = 'repository';
  const FIELD_RULE                   = 'rule';
  const FIELD_AFFECTED_PACKAGE       = 'affected-package';
  const FIELD_AFFECTED_PACKAGE_OWNER = 'affected-package-owner';

  const CONDITION_CONTAINS      = 'contains';
  const CONDITION_NOT_CONTAINS  = '!contains';
  const CONDITION_IS            = 'is';
  const CONDITION_IS_NOT        = '!is';
  const CONDITION_IS_ANY        = 'isany';
  const CONDITION_IS_NOT_ANY    = '!isany';
  const CONDITION_INCLUDE_ALL   = 'all';
  const CONDITION_INCLUDE_ANY   = 'any';
  const CONDITION_INCLUDE_NONE  = 'none';
  const CONDITION_IS_ME         = 'me';
  const CONDITION_IS_NOT_ME     = '!me';
  const CONDITION_REGEXP        = 'regexp';
  const CONDITION_RULE          = 'conditions';
  const CONDITION_NOT_RULE      = '!conditions';
  const CONDITION_EXISTS        = 'exists';
  const CONDITION_NOT_EXISTS    = '!exists';
  const CONDITION_REGEXP_PAIR   = 'regexp-pair';

  const ACTION_ADD_CC     = 'addcc';
  const ACTION_REMOVE_CC  = 'remcc';
  const ACTION_EMAIL      = 'email';
  const ACTION_NOTHING    = 'nothing';
  const ACTION_AUDIT      = 'audit';
  const ACTION_FLAG       = 'flag';

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

  abstract public function getPHID();
  abstract public function getHeraldName();
  abstract public function getHeraldField($field_name);
  abstract public function applyHeraldEffects(array $effects);

  public function isEnabled() {
    return true;
  }

  /**
   * NOTE: You generally should not override this; it exists to support legacy
   * adapters which had hard-coded content types.
   */
  public function getAdapterContentType() {
    return get_class($this);
  }

  abstract public function getAdapterContentName();


/* -(  Fields  )------------------------------------------------------------- */


  abstract public function getFields();

  public function getFieldNameMap() {
    return array(
      self::FIELD_TITLE => pht('Title'),
      self::FIELD_BODY => pht('Body'),
      self::FIELD_AUTHOR => pht('Author'),
      self::FIELD_REVIEWER => pht('Reviewer'),
      self::FIELD_REVIEWERS => pht('Reviewers'),
      self::FIELD_CC => pht('CCs'),
      self::FIELD_TAGS => pht('Tags'),
      self::FIELD_DIFF_FILE => pht('Any changed filename'),
      self::FIELD_DIFF_CONTENT => pht('Any changed file content'),
      self::FIELD_REPOSITORY => pht('Repository'),
      self::FIELD_RULE => pht('Another Herald rule'),
      self::FIELD_AFFECTED_PACKAGE => pht('Any affected package'),
      self::FIELD_AFFECTED_PACKAGE_OWNER =>
        pht("Any affected package's owner"),
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
      default:
        throw new Exception(
          "This adapter does not define conditions for field '{$field}'!");
    }
  }


/* -(  Actions  )------------------------------------------------------------ */

  abstract public function getActions($rule_type);

  public function getActionNameMap($rule_type) {
    switch ($rule_type) {
      case HeraldRuleTypeConfig::RULE_TYPE_GLOBAL:
        return array(
          self::ACTION_NOTHING    => pht('Do nothing'),
          self::ACTION_ADD_CC     => pht('Add emails to CC'),
          self::ACTION_REMOVE_CC  => pht('Remove emails from CC'),
          self::ACTION_EMAIL      => pht('Send an email to'),
          self::ACTION_AUDIT      => pht('Trigger an Audit by'),
          self::ACTION_FLAG       => pht('Mark with flag'),
        );
      case HeraldRuleTypeConfig::RULE_TYPE_PERSONAL:
        return array(
          self::ACTION_NOTHING    => pht('Do nothing'),
          self::ACTION_ADD_CC     => pht('Add me to CC'),
          self::ACTION_REMOVE_CC  => pht('Remove me from CC'),
          self::ACTION_EMAIL      => pht('Send me an email'),
          self::ACTION_AUDIT      => pht('Trigger an Audit by me'),
          self::ACTION_FLAG       => pht('Mark with flag'),
        );
      default:
        throw new Exception("Unknown rule type '{$rule_type}'!");
    }
  }


/* -(  Values  )------------------------------------------------------------- */


  public function getValueTypeForFieldAndCondition($field, $condition) {
    switch ($condition) {
      case self::CONDITION_CONTAINS:
      case self::CONDITION_NOT_CONTAINS:
      case self::CONDITION_IS:
      case self::CONDITION_IS_NOT:
      case self::CONDITION_REGEXP:
      case self::CONDITION_REGEXP_PAIR:
        return self::VALUE_TEXT;
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
          return self::VALUE_NONE;
        case self::ACTION_FLAG:
          return self::VALUE_FLAG_COLOR;
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
          return self::VALUE_PROJECT;
        case self::ACTION_FLAG:
          return self::VALUE_FLAG_COLOR;
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

    $handle = PhabricatorObjectHandleData::loadOneHandle(
      $phid,
      $user);

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

  public static function getAllEnabledAdapters() {
    $adapters = self::getAllAdapters();
    foreach ($adapters as $key => $adapter) {
      if (!$adapter->isEnabled()) {
        unset($adapters[$key]);
      }
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

  public static function getEnabledAdapterMap() {
    $map = array();

    $adapters = HeraldAdapter::getAllEnabledAdapters();
    foreach ($adapters as $adapter) {
      $type = $adapter->getAdapterContentType();
      $name = $adapter->getAdapterContentName();
      $map[$type] = $name;
    }

    asort($map);
    return $map;
  }



}

