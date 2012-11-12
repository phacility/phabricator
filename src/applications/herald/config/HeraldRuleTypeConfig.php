<?php

final class HeraldRuleTypeConfig {

  const RULE_TYPE_GLOBAL = 'global';
  const RULE_TYPE_PERSONAL = 'personal';

  public static function getRuleTypeMap() {
    static $map = array(
      self::RULE_TYPE_GLOBAL     => 'Global',
      self::RULE_TYPE_PERSONAL   => 'Personal',
    );
    return $map;
  }
}
