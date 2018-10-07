<?php

$commit_table = new PhabricatorRepositoryCommit();
$commit_conn = $commit_table->establishConnection('w');
$commit_name = $commit_table->getTableName();

$properties_table = new PhabricatorMetaMTAMailProperties();
$conn = $properties_table->establishConnection('w');

$iterator = new LiskRawMigrationIterator($commit_conn, $commit_name);
foreach ($iterator as $commit) {
  queryfx(
    $conn,
    'INSERT IGNORE INTO %T
        (objectPHID, mailProperties, dateCreated, dateModified)
      VALUES
        (%s, %s, %d, %d)',
    $properties_table->getTableName(),
    $commit['phid'],
    phutil_json_encode(
      array(
        'mailKey' => $commit['mailKey'],
      )),
    PhabricatorTime::getNow(),
    PhabricatorTime::getNow());
}
