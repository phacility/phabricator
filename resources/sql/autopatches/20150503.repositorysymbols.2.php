<?php

$projects = id(new PhabricatorRepositoryArcanistProjectQuery())
  ->setViewer(PhabricatorUser::getOmnipotentUser())
  ->needRepositories(true)
  ->execute();

$table = new PhabricatorRepositorySymbol();
$conn_w = $table->establishConnection('w');

foreach ($projects as $project) {
  $repo = $project->getRepository();

  if (!$repo) {
    continue;
  }

  echo pht("Migrating symbols for '%s' project...\n", $project->getName());

  queryfx(
    $conn_w,
    'UPDATE %T SET repositoryPHID = %s WHERE arcanistProjectID = %d',
    $table->getTableName(),
    $repo->getPHID(),
    $project->getID());
}
