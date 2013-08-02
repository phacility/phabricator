<?php

$table = new HeraldRule();
$conn_w = $table->establishConnection('w');

echo "Assigning PHIDs to Herald Rules...\n";

foreach (new LiskMigrationIterator(new HeraldRule()) as $rule) {
  $id = $rule->getID();
  echo "Rule {$id}.\n";

  if ($rule->getPHID()) {
    continue;
  }

  queryfx(
    $conn_w,
    'UPDATE %T SET phid = %s WHERE id = %d',
    $table->getTableName(),
    PhabricatorPHID::generateNewPHID(HeraldPHIDTypeRule::TYPECONST),
    $rule->getID());
}

echo "Done.\n";
