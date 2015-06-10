<?php

$table_w = new PhabricatorRepository();
$conn_w = $table_w->establishConnection('w');

// Repository and Project share a database.
$conn_r = $table_w->establishConnection('r');
$projects_table = 'repository_arcanistproject';

$raw_projects_data = queryfx_all($conn_r, 'SELECT * FROM %T', $projects_table);
$raw_projects_data = ipull($raw_projects_data, null, 'id');

$repository_ids = ipull($raw_projects_data, 'repositoryID');
if (!$repository_ids) {
  return;
}

$repositories = id(new PhabricatorRepositoryQuery())
  ->setViewer(PhabricatorUser::getOmnipotentUser())
  ->withIDs($repository_ids)
  ->execute();

$projects_to_repo_ids_map = ipull($raw_projects_data, 'repositoryID', 'phid');
$projects_to_repos_map = array();
foreach ($projects_to_repo_ids_map as $projectPHID => $repositoryID) {
  $repo = idx($repositories, $repositoryID);
  if ($repo) {
    $projects_to_repos_map[$projectPHID] = $repo->getPHID();
  }
}

foreach ($raw_projects_data as $project_row) {
  $repositoryID = idx($project_row, 'repositoryID');
  $repo = idx($repositories, $repositoryID);

  if (!$repo) {
    continue;
  }

  echo pht(
    "Migrating symbols configuration for '%s' project...\n",
    idx($project_row, 'name', '???'));

  $symbol_index_projects = $project_row['symbolIndexProjects'];
  $symbol_index_projects = nonempty($symbol_index_projects, '[]');
  try {
    $symbol_index_projects = phutil_json_decode($symbol_index_projects);
  } catch (PhutilJSONParserException $ex) {
    continue;
  }

  $sources = $repo->getDetail('symbol-sources', array());
  foreach ($symbol_index_projects as $index_project) {
    $sources[] = idx($projects_to_repos_map, $index_project);
  }
  $sources = array_filter($sources);
  $sources = array_unique($sources);

  $repo->setDetail('symbol-sources', $sources);

  $languages = $project_row['symbolIndexLanguages'];
  $languages = nonempty($languages, '[]');
  try {
    $languages = phutil_json_decode($languages);
  } catch (PhutilJSONParserException $ex) {
    continue;
  }

  $languages = array_merge(
    $repo->getDetail('symbol-languages', array()),
    $languages);
  $languages = array_unique($languages);

  $repo->setDetail('symbol-languages', $languages);

  queryfx(
    $conn_w,
    'UPDATE %T SET details = %s WHERE id = %d',
    $table_w->getTableName(),
    json_encode($repo->getDetails()),
    $repo->getID());
}
