<?php

$table = new HeraldRule();
$conn_w = $table->establishConnection('w');

echo pht('Assigning PHIDs to Herald Rules...')."\n";

foreach (new LiskMigrationIterator(new HeraldRule()) as $rule) {
  $id = $rule->getID();
  echo pht('Rule %d.', $id)."\n";

  if ($rule->getPHID()) {
    continue;
  }

  queryfx(
    $conn_w,
    'UPDATE %T SET phid = %s WHERE id = %d',
    $table->getTableName(),
    PhabricatorPHID::generateNewPHID(HeraldRulePHIDType::TYPECONST),
    $rule->getID());
}

echo pht('Done.')."\n";
