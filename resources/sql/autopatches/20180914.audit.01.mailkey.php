<?php

$commit_table = new PhabricatorRepositoryCommit();
$commit_conn = $commit_table->establishConnection('w');
$commit_name = $commit_table->getTableName();

$properties_table = new PhabricatorMetaMTAMailProperties();
$conn = $properties_table->establishConnection('w');

$iterator = new LiskRawMigrationIterator($commit_conn, $commit_name);
$chunks = new PhutilChunkedIterator($iterator, 100);
foreach ($chunks as $chunk) {
  $sql = array();
  foreach ($chunk as $commit) {
    $sql[] = qsprintf(
      $conn,
      '(%s, %s, %d, %d)',
      $commit['phid'],
      phutil_json_encode(
        array(
          'mailKey' => $commit['mailKey'],
        )),
      PhabricatorTime::getNow(),
      PhabricatorTime::getNow());
  }

  queryfx(
    $conn,
    'INSERT IGNORE INTO %R
        (objectPHID, mailProperties, dateCreated, dateModified)
      VALUES %LQ',
    $properties_table,
    $sql);
}
