<?php

$names = array(
  'CommandBuildStepImplementation',
  'LeaseHostBuildStepImplementation',
  'PublishFragmentBuildStepImplementation',
  'SleepBuildStepImplementation',
  'UploadArtifactBuildStepImplementation',
  'WaitForPreviousBuildStepImplementation',
);

$tables = array(
  id(new HarbormasterBuildStep())->getTableName(),
  id(new HarbormasterBuildTarget())->getTableName(),
);

echo "Renaming Harbormaster classes...\n";

$conn_w = id(new HarbormasterBuildStep())->establishConnection('w');
foreach ($names as $name) {
  $old = $name;
  $new = 'Harbormaster'.$name;

  echo "Renaming {$old} -> {$new}...\n";
  foreach ($tables as $table) {
    queryfx(
      $conn_w,
      'UPDATE %T SET className = %s WHERE className = %s',
      $table,
      $new,
      $old);
  }
}

echo "Done.\n";
