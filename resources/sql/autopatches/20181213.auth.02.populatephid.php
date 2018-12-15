<?php

$table = new PhabricatorAuthSession();
$iterator = new LiskMigrationIterator($table);
$conn = $table->establishConnection('w');

foreach ($iterator as $session) {
  if (strlen($session->getPHID())) {
    continue;
  }

  queryfx(
    $conn,
    'UPDATE %R SET phid = %s WHERE id = %d',
    $table,
    $session->generatePHID(),
    $session->getID());
}
