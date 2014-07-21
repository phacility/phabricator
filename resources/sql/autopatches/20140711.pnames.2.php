<?php

echo "Updating project datasource tokens...\n";

foreach (new LiskMigrationIterator(new PhabricatorProject()) as $project) {
  $name = $project->getName();
  echo "Updating project '{$name}'...\n";
  $project->updateDatasourceTokens();
}

echo "Done.\n";
