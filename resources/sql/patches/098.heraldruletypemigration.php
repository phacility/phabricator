<?php

echo pht("Checking for rules that can be converted to 'personal'.")."\n";
$table = new HeraldRule();
$table->openTransaction();
$table->beginReadLocking();

$rules = $table->loadAll();

foreach ($rules as $rule) {
  if ($rule->getRuleType() !== HeraldRuleTypeConfig::RULE_TYPE_PERSONAL) {
    $actions = $rule->loadActions();
    $can_be_personal = true;
    foreach ($actions as $action) {
      $target = $action->getTarget();
      if (is_array($target)) {
        if (count($target) > 1) {
          $can_be_personal = false;
          break;
        } else {
          $targetPHID = head($target);
          if ($targetPHID !== $rule->getAuthorPHID()) {
            $can_be_personal = false;
            break;
          }
        }
      } else if ($target) {
        if ($target !== $rule->getAuthorPHID()) {
          $can_be_personal = false;
          break;
        }
      }
    }

    if ($can_be_personal) {
      $rule->setRuleType(HeraldRuleTypeConfig::RULE_TYPE_PERSONAL);
      queryfx(
        $rule->establishConnection('w'),
        'UPDATE %T SET ruleType = %s WHERE id = %d',
        $rule->getTableName(),
        $rule->getRuleType(),
        $rule->getID());

      echo pht("Setting rule '%s' to personal.", $rule->getName())."\n";
    }
  }
}

$table->endReadLocking();
$table->saveTransaction();
echo pht('Done.')."\n";
