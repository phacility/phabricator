<?php

// Prior to this patch, we issued sessions "web-1", "web-2", etc., up to some
// limit. This collapses all the "web-X" sessions into "web" sessions.

$session_table = new PhabricatorAuthSession();
$conn_w = $session_table->establishConnection('w');

foreach (new LiskMigrationIterator($session_table) as $session) {
  $id = $session->getID();

  echo pht('Migrating session %d...', $id)."\n";
  $old_type = $session->getType();
  $new_type = preg_replace('/-.*$/', '', $old_type);

  if ($old_type !== $new_type) {
    queryfx(
      $conn_w,
      'UPDATE %T SET type = %s WHERE id = %d',
      $session_table->getTableName(),
      $new_type,
      $id);
  }
}

echo pht('Done.')."\n";
