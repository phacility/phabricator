<?php

$diff_table = new DifferentialDiff();
$conn_w = $diff_table->establishConnection('w');

$size = 1000;

$row_iter = id(new LiskMigrationIterator($diff_table))->setPageSize($size);
$chunk_iter = new PhutilChunkedIterator($row_iter, $size);

foreach ($chunk_iter as $chunk) {
  $sql = array();

  foreach ($chunk as $diff) {
    $id = $diff->getID();
    echo pht('Migrating diff ID %d...', $id)."\n";

    $phid = $diff->getPHID();
    if (strlen($phid)) {
      continue;
    }

    $type_diff = DifferentialDiffPHIDType::TYPECONST;
    $new_phid = PhabricatorPHID::generateNewPHID($type_diff);

    $sql[] = qsprintf(
      $conn_w,
      '(%d, %s)',
      $id,
      $new_phid);
  }

  if (!$sql) {
    continue;
  }

  foreach (PhabricatorLiskDAO::chunkSQL($sql, ', ') as $sql_chunk) {
    queryfx(
      $conn_w,
      'INSERT IGNORE INTO %T (id, phid) VALUES %Q
        ON DUPLICATE KEY UPDATE phid = VALUES(phid)',
      $diff_table->getTableName(),
      $sql_chunk);
  }
}

echo pht('Done.')."\n";
