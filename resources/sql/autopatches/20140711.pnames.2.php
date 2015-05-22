<?php

echo pht('Updating project datasource tokens...')."\n";

foreach (new LiskMigrationIterator(new PhabricatorProject()) as $project) {
  $name = $project->getName();
  echo pht("Updating project '%d'...", $name)."\n";
  $project->updateDatasourceTokens();
}

echo pht('Done.')."\n";
