<?php

$table = new ManiphestTransaction();
$conn_w = $table->establishConnection('w');

echo "Converting Maniphest project transactions to modern EDGE ".
  "transactions...\n";
$metadata = array(
  'edge:type' => PhabricatorProjectObjectHasProjectEdgeType::EDGECONST,
);
foreach (new LiskMigrationIterator($table) as $txn) {
  // ManiphestTransaction::TYPE_PROJECTS
  if ($txn->getTransactionType() == 'projects') {
    $old_value = mig20141222_build_edge_data(
      $txn->getOldValue(),
      $txn->getObjectPHID());
    $new_value = mig20141222_build_edge_data(
      $txn->getNewvalue(),
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
}

echo "Done.\n";

function mig20141222_build_edge_data(array $project_phids, $task_phid) {
  $edge_data = array();
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
