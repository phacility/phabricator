<?php

// See T13208. It was previously possible to replace a saved query with another
// saved query, causing loss of the first query. Find projects which have their
// default query set to an invalid query and throw the setting away.

$viewer = PhabricatorUser::getOmnipotentUser();

$table = new PhabricatorProject();
$conn = $table->establishConnection('w');

$iterator = new LiskMigrationIterator($table);
$search_engine = id(new ManiphestTaskSearchEngine())
  ->setViewer($viewer);

foreach ($iterator as $project) {
  $default_filter = $project->getDefaultWorkboardFilter();
  if (!strlen($default_filter)) {
    continue;
  }

  if ($search_engine->isBuiltinQuery($default_filter)) {
    continue;
  }

  $saved = id(new PhabricatorSavedQueryQuery())
    ->setViewer($viewer)
    ->withQueryKeys(array($default_filter))
    ->executeOne();
  if ($saved) {
    continue;
  }

  $properties = $project->getProperties();
  unset($properties['workboard.filter.default']);

  queryfx(
    $conn,
    'UPDATE %T SET properties = %s WHERE id = %d',
    $table->getTableName(),
    phutil_json_encode($properties),
    $project->getID());

  echo tsprintf(
    "%s\n",
    pht(
      'Project ("%s") had an invalid query saved as a default workboard '.
      'query. The query has been reset. See T13208.',
      $project->getDisplayName()));
}
