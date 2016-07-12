<?php

$table = new HarbormasterBuildPlan();
$conn_w = $table->establishConnection('w');
$viewer = PhabricatorUser::getOmnipotentUser();

// Since HarbormasterBuildStepQuery has been updated to handle the
// correct order, we can't use the built in database access.

foreach (new LiskMigrationIterator($table) as $plan) {
  $planname = $plan->getName();
  echo pht('Migrating steps in %s...', $planname)."\n";

  $rows = queryfx_all(
    $conn_w,
    'SELECT id, sequence FROM harbormaster_buildstep '.
    'WHERE buildPlanPHID = %s '.
    'ORDER BY id ASC',
    $plan->getPHID());

  $sequence = 1;
  foreach ($rows as $row) {
    $id = $row['id'];
    $existing = $row['sequence'];
    if ($existing != 0) {
      echo "  - ".pht('%d (already migrated)...', $id)."\n";
      continue;
    }
    echo "  - ".pht('%d to position %s...', $id, $sequence)."\n";
    queryfx(
      $conn_w,
      'UPDATE harbormaster_buildstep '.
      'SET sequence = %d '.
      'WHERE id = %d',
      $sequence,
      $id);
    $sequence++;
  }
}

echo pht('Done.')."\n";
