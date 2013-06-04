<?php

final class HeraldRuleTypeConfig {

  const RULE_TYPE_GLOBAL = 'global';
  const RULE_TYPE_PERSONAL = 'personal';

  public static function getRuleTypeMap() {
    $map = array(
      self::RULE_TYPE_GLOBAL     => pht('Global'),
      self::RULE_TYPE_PERSONAL   => pht('Personal'),
    );
    return $map;
  }
}
