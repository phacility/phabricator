<?php

// Was PhabricatorEdgeConfig::TYPE_COLUMN_HAS_OBJECT
$type_has_object = 44;

$column = new PhabricatorProjectColumn();
$conn_w = $column->establishConnection('w');

$rows = queryfx_all(
  $conn_w,
  'SELECT src, dst FROM %T WHERE type = %d',
  PhabricatorEdgeConfig::TABLE_NAME_EDGE,
  $type_has_object);

$cols = array();
foreach ($rows as $row) {
  $cols[$row['src']][] = $row['dst'];
}

$sql = array();
foreach ($cols as $col_phid => $obj_phids) {
  echo pht("Migrating column '%s'...", $col_phid)."\n";
  $column = id(new PhabricatorProjectColumn())->loadOneWhere(
    'phid = %s',
    $col_phid);
  if (!$column) {
    echo pht("Column '%s' does not exist.", $col_phid)."\n";
    continue;
  }

  $sequence = 0;
  foreach ($obj_phids as $obj_phid) {
    $sql[] = qsprintf(
      $conn_w,
      '(%s, %s, %s, %d)',
      $column->getProjectPHID(),
      $column->getPHID(),
      $obj_phid,
      $sequence++);
  }
}

echo pht('Inserting rows...')."\n";
foreach (PhabricatorLiskDAO::chunkSQL($sql) as $chunk) {
  queryfx(
    $conn_w,
    'INSERT INTO %T (boardPHID, columnPHID, objectPHID, sequence)
      VALUES %Q',
    id(new PhabricatorProjectColumnPosition())->getTableName(),
    $chunk);
}

echo pht('Done.')."\n";
