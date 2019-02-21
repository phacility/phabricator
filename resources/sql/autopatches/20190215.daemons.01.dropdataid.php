<?php

// See T6615. We're about to change the nullability on the "dataID" column,
// but it may have a UNIQUE KEY on it. Make sure we get rid of this key first
// so we don't run into trouble.

// There's no "IF EXISTS" modifier for "ALTER TABLE" so run this as a PHP patch
// instead of an SQL patch.

$table = new PhabricatorWorkerActiveTask();
$conn = $table->establishConnection('w');

try {
  queryfx(
    $conn,
    'ALTER TABLE %R DROP KEY %T',
    $table,
    'dataID');
} catch (AphrontQueryException $ex) {
  // Ignore.
}
