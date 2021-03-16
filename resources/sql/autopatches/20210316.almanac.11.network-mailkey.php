<?php

$network_table = new AlmanacNetwork();
$network_conn = $network_table->establishConnection('w');

$properties_table = new PhabricatorMetaMTAMailProperties();
$conn = $properties_table->establishConnection('w');

$iterator = new LiskRawMigrationIterator(
  $network_conn,
  $network_table->getTableName());

foreach ($iterator as $row) {
  queryfx(
    $conn,
    'INSERT IGNORE INTO %R
        (objectPHID, mailProperties, dateCreated, dateModified)
      VALUES
        (%s, %s, %d, %d)',
    $properties_table,
    $row['phid'],
    phutil_json_encode(
      array(
        'mailKey' => $row['mailKey'],
      )),
    PhabricatorTime::getNow(),
    PhabricatorTime::getNow());
}
