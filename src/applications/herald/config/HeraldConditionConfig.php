<?php

final class HeraldConditionConfig {

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
  const CONDITION_REGEXP_PAIR     = 'regexp-pair';

  public static function getConditionMap() {
    $map = array(
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

    return $map;
  }

  public static function getConditionMapForField($field) {
    $map = self::getConditionMap();
    switch ($field) {
      case HeraldFieldConfig::FIELD_TITLE:
      case HeraldFieldConfig::FIELD_BODY:
        return array_select_keys(
          $map,
          array(
            self::CONDITION_CONTAINS,
            self::CONDITION_NOT_CONTAINS,
            self::CONDITION_IS,
            self::CONDITION_IS_NOT,
            self::CONDITION_REGEXP,
          ));
      case HeraldFieldConfig::FIELD_AUTHOR:
      case HeraldFieldConfig::FIELD_REPOSITORY:
      case HeraldFieldConfig::FIELD_REVIEWER:
      case HeraldFieldConfig::FIELD_MERGE_REQUESTER:
        return array_select_keys(
          $map,
          array(
            self::CONDITION_IS_ANY,
            self::CONDITION_IS_NOT_ANY,
          ));
      case HeraldFieldConfig::FIELD_TAGS:
      case HeraldFieldConfig::FIELD_REVIEWERS:
      case HeraldFieldConfig::FIELD_CC:
      case HeraldFieldConfig::FIELD_DIFFERENTIAL_REVIEWERS:
      case HeraldFieldConfig::FIELD_DIFFERENTIAL_CCS:
        return array_select_keys(
          $map,
          array(
            self::CONDITION_INCLUDE_ALL,
            self::CONDITION_INCLUDE_ANY,
            self::CONDITION_INCLUDE_NONE,
          ));
      case HeraldFieldConfig::FIELD_DIFF_FILE:
        return array_select_keys(
          $map,
          array(
            self::CONDITION_CONTAINS,
            self::CONDITION_REGEXP,
          ));
      case HeraldFieldConfig::FIELD_DIFF_CONTENT:
        return array_select_keys(
          $map,
          array(
            self::CONDITION_CONTAINS,
            self::CONDITION_REGEXP,
            self::CONDITION_REGEXP_PAIR,
          ));
      case HeraldFieldConfig::FIELD_RULE:
        return array_select_keys(
          $map,
          array(
            self::CONDITION_RULE,
            self::CONDITION_NOT_RULE,
          ));
      case HeraldFieldConfig::FIELD_AFFECTED_PACKAGE:
      case HeraldFieldConfig::FIELD_AFFECTED_PACKAGE_OWNER:
      case HeraldFieldConfig::FIELD_NEED_AUDIT_FOR_PACKAGE:
        return array_select_keys(
          $map,
          array(
            self::CONDITION_INCLUDE_ANY,
            self::CONDITION_INCLUDE_NONE,
          ));
      case HeraldFieldConfig::FIELD_DIFFERENTIAL_REVISION:
        return array_select_keys(
          $map,
          array(
            self::CONDITION_EXISTS,
            self::CONDITION_NOT_EXISTS,
          ));
      default:
        throw new Exception("Unknown field type '{$field}'.");
    }
  }

}
