<?php

$table = new PhabricatorProject();
$conn_w = $table->establishConnection('w');

foreach (new LiskMigrationIterator($table) as $project) {
  $path = $project->getProjectPath();
  $key = $project->getProjectPathKey();

  if (strlen($path) && ($key !== "\0\0\0\0")) {
    continue;
  }

  $path_key = PhabricatorHash::digestForIndex($project->getPHID());
  $path_key = substr($path_key, 0, 4);

  queryfx(
    $conn_w,
    'UPDATE %T SET projectPath = %s, projectPathKey = %s WHERE id = %d',
    $project->getTableName(),
    $path_key,
    $path_key,
    $project->getID());
}
