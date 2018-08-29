<?php

$document_table = new PhrictionDocument();
$document_conn = $document_table->establishConnection('w');
$document_name = $document_table->getTableName();

$properties_table = new PhabricatorMetaMTAMailProperties();
$conn = $properties_table->establishConnection('w');

$iterator = new LiskRawMigrationIterator($document_conn, $document_name);
foreach ($iterator as $row) {
  queryfx(
    $conn,
    'INSERT IGNORE INTO %T
        (objectPHID, mailProperties, dateCreated, dateModified)
      VALUES
        (%s, %s, %d, %d)',
    $properties_table->getTableName(),
    $row['phid'],
    phutil_json_encode(
      array(
        'mailKey' => $row['mailKey'],
      )),
    PhabricatorTime::getNow(),
    PhabricatorTime::getNow());
}
