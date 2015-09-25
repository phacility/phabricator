<?php

$table = new ManiphestTransaction();
$conn_w = $table->establishConnection('w');

echo pht(
  "Converting Maniphest project transactions to modern edge transactions...\n");
$metadata = array(
  'edge:type' => PhabricatorProjectObjectHasProjectEdgeType::EDGECONST,
);
foreach (new LiskMigrationIterator($table) as $txn) {
  if ($txn->getTransactionType() != 'projects') {
    continue;
  }

  $old_value = mig20141222_build_edge_data(
    $txn->getOldValue(),
    $txn->getObjectPHID());

  $new_value = mig20141222_build_edge_data(
    $txn->getNewValue(),
    $txn->getObjectPHID());

  queryfx(
    $conn_w,
    'UPDATE %T SET '.
      'transactionType = %s, oldValue = %s, newValue = %s, metaData = %s '.
    'WHERE id = %d',
    $table->getTableName(),
    PhabricatorTransactions::TYPE_EDGE,
    json_encode($old_value),
    json_encode($new_value),
    json_encode($metadata),
    $txn->getID());
}

echo pht('Done.')."\n";

function mig20141222_build_edge_data($project_phids, $task_phid) {
  $edge_data = array();

  // See T9464. If we didn't get a proper array value out of the transaction,
  // just return an empty value so we can move forward.
  if (!is_array($project_phids)) {
    return $edge_data;
  }

  foreach ($project_phids as $project_phid) {
    if (!is_scalar($project_phid)) {
      continue;
    }

    $edge_data[$project_phid] = array(
      'src' => $task_phid,
      'type' => PhabricatorProjectObjectHasProjectEdgeType::EDGECONST,
      'dst' => $project_phid,
    );
  }

  return $edge_data;
}
