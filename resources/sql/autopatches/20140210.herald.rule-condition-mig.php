<?php

$table = new HeraldCondition();
$conn_w = $table->establishConnection('w');

echo pht(
  "Migrating Herald conditions of type Herald rule from IDs to PHIDs...\n");
foreach (new LiskMigrationIterator($table) as $condition) {
  if ($condition->getFieldName() != HeraldAdapter::FIELD_RULE) {
    continue;
  }

  $value = $condition->getValue();
  if (!is_numeric($value)) {
    continue;
  }
  $id = $condition->getID();
  echo pht('Updating condition %s...', $id)."\n";

  $rule = id(new HeraldRuleQuery())
    ->setViewer(PhabricatorUser::getOmnipotentUser())
    ->withIDs(array($value))
    ->executeOne();

  queryfx(
    $conn_w,
    'UPDATE %T SET value = %s WHERE id = %d',
    $table->getTableName(),
    json_encode($rule->getPHID()),
    $id);
}
echo pht('Done.')."\n";
