<?php

$table = new PhabricatorSavedQuery();
$conn = $table->establishConnection('w');

$status_map = array(
  0 => 'none',
  1 => 'needs-audit',
  2 => 'concern-raised',
  3 => 'partially-audited',
  4 => 'audited',
  5 => 'needs-verification',
);

foreach (new LiskMigrationIterator($table) as $query) {
  if ($query->getEngineClassName() !== 'PhabricatorCommitSearchEngine') {
    continue;
  }

  $parameters = $query->getParameters();
  $status = idx($parameters, 'statuses');

  if (!$status) {
    // No saved "status" constraint.
    continue;
  }

  if (!is_array($status)) {
    // Saved constraint isn't a list.
    continue;
  }

  // Migrate old integer values to new string values.
  $old_status = $status;
  foreach ($status as $key => $value) {
    if (is_numeric($value)) {
      $status[$key] = $status_map[$value];
    }
  }

  if ($status === $old_status) {
    // Nothing changed.
    continue;
  }

  $parameters['statuses'] = $status;

  queryfx(
    $conn,
    'UPDATE %T SET parameters = %s WHERE id = %d',
    $table->getTableName(),
    phutil_json_encode($parameters),
    $query->getID());
}
