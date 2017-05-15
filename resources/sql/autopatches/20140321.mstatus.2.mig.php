<?php

$status_map = array(
  0 => 'open',
  1 => 'resolved',
  2 => 'wontfix',
  3 => 'invalid',
  4 => 'duplicate',
  5 => 'spite',
);

$conn_w = id(new ManiphestTask())->establishConnection('w');

echo pht('Migrating tasks to new status constants...')."\n";
foreach (new LiskMigrationIterator(new ManiphestTask()) as $task) {
  $id = $task->getID();
  echo pht('Migrating %s...', "T{$id}")."\n";

  $status = $task->getStatus();
  if (isset($status_map[$status])) {
    queryfx(
      $conn_w,
      'UPDATE %T SET status = %s WHERE id = %d',
      $task->getTableName(),
      $status_map[$status],
      $id);
  }
}

echo pht('Done.')."\n";


echo pht('Migrating task transactions to new status constants...')."\n";
foreach (new LiskMigrationIterator(new ManiphestTransaction()) as $xaction) {
  $id = $xaction->getID();
  echo pht('Migrating %d...', $id)."\n";

  $xn_type = ManiphestTaskStatusTransaction::TRANSACTIONTYPE;
  if ($xaction->getTransactionType() == $xn_type) {
    $old = $xaction->getOldValue();
    if ($old !== null && isset($status_map[$old])) {
      $old = $status_map[$old];
    }

    $new = $xaction->getNewValue();
    if (isset($status_map[$new])) {
      $new = $status_map[$new];
    }

    queryfx(
      $conn_w,
      'UPDATE %T SET oldValue = %s, newValue = %s WHERE id = %d',
      $xaction->getTableName(),
      json_encode($old),
      json_encode($new),
      $id);
  }
}
echo pht('Done.')."\n";

$conn_w = id(new PhabricatorSavedQuery())->establishConnection('w');

echo pht('Migrating searches to new status constants...')."\n";
foreach (new LiskMigrationIterator(new PhabricatorSavedQuery()) as $query) {
  $id = $query->getID();
  echo pht('Migrating %d...', $id)."\n";

  if ($query->getEngineClassName() !== 'ManiphestTaskSearchEngine') {
    continue;
  }

  $params = $query->getParameters();
  $statuses = idx($params, 'statuses', array());
  if ($statuses) {
    $changed = false;
    foreach ($statuses as $key => $status) {
      if (isset($status_map[$status])) {
        $statuses[$key] = $status_map[$status];
        $changed = true;
      }
    }

    if ($changed) {
      $params['statuses'] = $statuses;

      queryfx(
        $conn_w,
        'UPDATE %T SET parameters = %s WHERE id = %d',
        $query->getTableName(),
        json_encode($params),
        $id);
    }
  }
}
echo pht('Done.')."\n";
