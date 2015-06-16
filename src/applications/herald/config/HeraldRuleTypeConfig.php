<?php

final class HeraldRuleTypeConfig extends Phobject {

  const RULE_TYPE_GLOBAL = 'global';
  const RULE_TYPE_OBJECT = 'object';
  const RULE_TYPE_PERSONAL = 'personal';

  public static function getRuleTypeMap() {
    $map = array(
      self::RULE_TYPE_PERSONAL => pht('Personal'),
      self::RULE_TYPE_OBJECT => pht('Object'),
      self::RULE_TYPE_GLOBAL => pht('Global'),
    );
    return $map;
  }
}
