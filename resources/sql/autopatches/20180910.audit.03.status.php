<?php

$table = new PhabricatorRepositoryCommit();
$conn = $table->establishConnection('w');

$status_map = array(
  0 => 'none',
  1 => 'needs-audit',
  2 => 'concern-raised',
  3 => 'partially-audited',
  4 => 'audited',
  5 => 'needs-verification',
);

foreach (new LiskMigrationIterator($table) as $commit) {
  $status = $commit->getAuditStatus();

  if (!isset($status_map[$status])) {
    continue;
  }

  queryfx(
    $conn,
    'UPDATE %T SET auditStatus = %s WHERE id = %d',
    $table->getTableName(),
    $status_map[$status],
    $commit->getID());
}
