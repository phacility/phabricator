<?php

// Migrate saved Differential revision queries from using a "<select />"
// control with hard-coded status groups for status selection to using a
// tokenizer with status functions.

$table = new PhabricatorSavedQuery();
$conn = $table->establishConnection('w');

$status_map = array(
  'status-open' => array('open()'),
  'status-closed' => array('closed()'),

  'status-accepted' => array('accepted'),
  'status-needs-review' => array('needs-review'),
  'status-needs-revision' => array('needs-revision'),
  'status-abandoned' => array('abandoned'),
);

foreach (new LiskMigrationIterator($table) as $query) {
  if ($query->getEngineClassName() !== 'DifferentialRevisionSearchEngine') {
    // This isn't a revision query.
    continue;
  }

  $parameters = $query->getParameters();
  $status = idx($parameters, 'status');

  if (!$status) {
    // This query didn't specify a "status" value.
    continue;
  }

  if (!isset($status_map[$status])) {
    // The "status" value is unknown, or does not correspond to a
    // modern "status" constraint.
    continue;
  }

  $parameters['statuses'] = $status_map[$status];

  queryfx(
    $conn,
    'UPDATE %T SET parameters = %s WHERE id = %d',
    $table->getTableName(),
    phutil_json_encode($parameters),
    $query->getID());
}
