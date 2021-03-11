<?php

$phid_type = DifferentialChangesetPHIDType::TYPECONST;

$changeset_table = new DifferentialChangeset();

$conn = $changeset_table->establishConnection('w');
$table_name = $changeset_table->getTableName();

$chunk_size = 4096;

$temporary_table = 'tmp_20210215_changeset_id_map';

try {
  queryfx(
    $conn,
    'CREATE TEMPORARY TABLE %T (
      changeset_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
      changeset_phid VARBINARY(64) NOT NULL)',
    $temporary_table);
} catch (AphrontAccessDeniedQueryException $ex) {
  throw new PhutilProxyException(
    pht(
      'Failed to "CREATE TEMPORARY TABLE". You may need to "GRANT" the '.
      'current MySQL user this permission.'),
    $ex);
}

$table_iterator = id(new LiskRawMigrationIterator($conn, $table_name))
  ->setPageSize($chunk_size);

$chunk_iterator = new PhutilChunkedIterator($table_iterator, $chunk_size);
foreach ($chunk_iterator as $chunk) {

  $map = array();
  foreach ($chunk as $changeset_row) {
    $phid = $changeset_row['phid'];

    if (strlen($phid)) {
      continue;
    }

    $phid = PhabricatorPHID::generateNewPHID($phid_type);
    $id = $changeset_row['id'];

    $map[(int)$id] = $phid;
  }

  if (!$map) {
    continue;
  }

  $sql = array();
  foreach ($map as $changeset_id => $changeset_phid) {
    $sql[] = qsprintf(
      $conn,
      '(%d, %s)',
      $changeset_id,
      $changeset_phid);
  }

  queryfx(
    $conn,
    'TRUNCATE TABLE %T',
    $temporary_table);

  queryfx(
    $conn,
    'INSERT INTO %T (changeset_id, changeset_phid) VALUES %LQ',
    $temporary_table,
    $sql);

  queryfx(
    $conn,
    'UPDATE %T c JOIN %T x ON c.id = x.changeset_id
      SET c.phid = x.changeset_phid',
    $table_name,
    $temporary_table);
}
