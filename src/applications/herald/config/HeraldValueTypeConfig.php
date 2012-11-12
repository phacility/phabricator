<?php

final class HeraldValueTypeConfig {

  const VALUE_TEXT                = 'text';
  const VALUE_NONE                = 'none';
  const VALUE_EMAIL               = 'email';
  const VALUE_USER                = 'user';
  const VALUE_TAG                 = 'tag';
  const VALUE_RULE                = 'rule';
  const VALUE_REPOSITORY          = 'repository';
  const VALUE_OWNERS_PACKAGE      = 'package';
  const VALUE_PROJECT             = 'project';
  const VALUE_FLAG_COLOR          = 'flagcolor';

  public static function getValueTypeForFieldAndCondition($field, $condition) {
    switch ($condition) {
      case HeraldConditionConfig::CONDITION_CONTAINS:
      case HeraldConditionConfig::CONDITION_NOT_CONTAINS:
      case HeraldConditionConfig::CONDITION_IS:
      case HeraldConditionConfig::CONDITION_IS_NOT:
      case HeraldConditionConfig::CONDITION_REGEXP:
      case HeraldConditionConfig::CONDITION_REGEXP_PAIR:
        return self::VALUE_TEXT;
      case HeraldConditionConfig::CONDITION_IS_ANY:
      case HeraldConditionConfig::CONDITION_IS_NOT_ANY:
        switch ($field) {
          case HeraldFieldConfig::FIELD_REPOSITORY:
            return self::VALUE_REPOSITORY;
          default:
            return self::VALUE_USER;
        }
        break;
      case HeraldConditionConfig::CONDITION_INCLUDE_ALL:
      case HeraldConditionConfig::CONDITION_INCLUDE_ANY:
      case HeraldConditionConfig::CONDITION_INCLUDE_NONE:
        switch ($field) {
          case HeraldFieldConfig::FIELD_REPOSITORY:
            return self::VALUE_REPOSITORY;
          case HeraldFieldConfig::FIELD_CC:
          case HeraldFieldConfig::FIELD_DIFFERENTIAL_CCS:
            return self::VALUE_EMAIL;
          case HeraldFieldConfig::FIELD_TAGS:
            return self::VALUE_TAG;
          case HeraldFieldConfig::FIELD_AFFECTED_PACKAGE:
          case HeraldFieldConfig::FIELD_NEED_AUDIT_FOR_PACKAGE:
            return self::VALUE_OWNERS_PACKAGE;
          default:
            return self::VALUE_USER;
        }
        break;
      case HeraldConditionConfig::CONDITION_IS_ME:
      case HeraldConditionConfig::CONDITION_IS_NOT_ME:
      case HeraldConditionConfig::CONDITION_EXISTS:
      case HeraldConditionConfig::CONDITION_NOT_EXISTS:
        return self::VALUE_NONE;
      case HeraldConditionConfig::CONDITION_RULE:
      case HeraldConditionConfig::CONDITION_NOT_RULE:
        return self::VALUE_RULE;
      default:
        throw new Exception("Unknown condition.");
    }
  }

  public static function getValueTypeForAction($action, $rule_type) {
    $is_personal = ($rule_type == HeraldRuleTypeConfig::RULE_TYPE_PERSONAL);

    if ($is_personal) {
      switch ($action) {
        case HeraldActionConfig::ACTION_ADD_CC:
        case HeraldActionConfig::ACTION_REMOVE_CC:
        case HeraldActionConfig::ACTION_EMAIL:
        case HeraldActionConfig::ACTION_NOTHING:
        case HeraldActionConfig::ACTION_AUDIT:
          return self::VALUE_NONE;
        case HeraldActionConfig::ACTION_FLAG:
          return self::VALUE_FLAG_COLOR;
        default:
          throw new Exception("Unknown or invalid action '{$action}'.");
      }
    } else {
      switch ($action) {
        case HeraldActionConfig::ACTION_ADD_CC:
        case HeraldActionConfig::ACTION_REMOVE_CC:
        case HeraldActionConfig::ACTION_EMAIL:
          return self::VALUE_EMAIL;
        case HeraldActionConfig::ACTION_NOTHING:
          return self::VALUE_NONE;
        case HeraldActionConfig::ACTION_AUDIT:
          return self::VALUE_PROJECT;
        case HeraldActionConfig::ACTION_FLAG:
          return self::VALUE_FLAG_COLOR;
        default:
          throw new Exception("Unknown or invalid action '{$action}'.");
      }
    }
  }
}
