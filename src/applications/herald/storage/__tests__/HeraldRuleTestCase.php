<?php

final class HeraldRuleTestCase extends PhabricatorTestCase {

  public function testHeraldRuleExecutionOrder() {
    $rules = array(
      1 => HeraldRuleTypeConfig::RULE_TYPE_GLOBAL,
      2 => HeraldRuleTypeConfig::RULE_TYPE_GLOBAL,
      3 => HeraldRuleTypeConfig::RULE_TYPE_OBJECT,
      4 => HeraldRuleTypeConfig::RULE_TYPE_PERSONAL,
      5 => HeraldRuleTypeConfig::RULE_TYPE_GLOBAL,
      6 => HeraldRuleTypeConfig::RULE_TYPE_PERSONAL,
    );

    foreach ($rules as $id => $type) {
      $rules[$id] = id(new HeraldRule())
        ->setID($id)
        ->setRuleType($type);
    }

    shuffle($rules);
    $rules = msort($rules, 'getRuleExecutionOrderSortKey');
    $this->assertEqual(
      array(
        // Personal
        4,
        6,

        // Object
        3,

        // Global
        1,
        2,
        5,
      ),
      array_values(mpull($rules, 'getID')));
  }


}
