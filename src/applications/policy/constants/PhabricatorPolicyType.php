<?php

final class PhabricatorPolicyType extends PhabricatorPolicyConstants {

  const TYPE_GLOBAL       = 'global';
  const TYPE_CUSTOM       = 'custom';
  const TYPE_PROJECT      = 'project';
  const TYPE_MASKED       = 'masked';

  public static function getPolicyTypeOrder($type) {
    static $map = array(
      self::TYPE_GLOBAL   => 0,
      self::TYPE_CUSTOM   => 1,
      self::TYPE_PROJECT  => 2,
      self::TYPE_MASKED   => 9,
    );
    return idx($map, $type, 9);
  }

  public static function getPolicyTypeName($type) {
    switch ($type) {
      case self::TYPE_GLOBAL:
        return pht('Basic Policies');
      case self::TYPE_CUSTOM:
        return pht('Advanced');
      case self::TYPE_PROJECT:
        return pht('Members of Project...');
      case self::TYPE_MASKED:
      default:
        return pht('Other Policies');
    }
  }

}
