<?php

$plan_table = new HarbormasterBuildPlan();
$step_table = new HarbormasterBuildStep();
$conn_w = $plan_table->establishConnection('w');
foreach (new LiskMigrationIterator($plan_table) as $plan) {

  echo pht(
    "Migrating build plan %d: %s...\n",
    $plan->getID(),
    $plan->getName());

  // Load all build steps in order using the step sequence.
  $steps = queryfx_all(
    $conn_w,
    'SELECT id FROM %T WHERE buildPlanPHID = %s ORDER BY sequence ASC;',
    $step_table->getTableName(),
    $plan->getPHID());

  $previous_step = null;
  foreach ($steps as $step) {
    $id = $step['id'];

    $loaded_step = id(new HarbormasterBuildStep())->load($id);

    $depends_on = $loaded_step->getDetail('dependsOn');
    if ($depends_on !== null) {
      // This plan already contains steps with depends_on set, so
      // we skip since there's nothing to migrate.
      break;
    }

    if ($previous_step === null) {
      $depends_on = array();
    } else {
      $depends_on = array($previous_step->getPHID());
    }

    $loaded_step->setDetail('dependsOn', $depends_on);
    queryfx(
      $conn_w,
      'UPDATE %T SET details = %s WHERE id = %d',
      $step_table->getTableName(),
      json_encode($loaded_step->getDetails()),
      $loaded_step->getID());

    $previous_step = $loaded_step;

    echo pht(
      "  Migrated build step %d.\n",
      $loaded_step->getID());
  }

}
