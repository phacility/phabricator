<?php

$table = new PhabricatorRepositorySymbol();
$conn_w = $table->establishConnection('w');

$projects = queryfx_all(
  $conn_w,
  'SELECT * FROM %T',
  'repository_arcanistproject');

foreach ($projects as $project) {
  $repo = id(new PhabricatorRepositoryQuery())
    ->setViewer(PhabricatorUser::getOmnipotentUser())
    ->withIDs(array($project['repositoryID']))
    ->executeOne();

  if (!$repo) {
    continue;
  }

  echo pht("Migrating symbols for '%s' project...\n", $project['name']);

  queryfx(
    $conn_w,
    'UPDATE %T SET repositoryPHID = %s WHERE arcanistProjectID = %d',
    $table->getTableName(),
    $repo->getPHID(),
    $project['id']);
}
