<?php

echo "Ensuring project names are unique enough...\n";
$table = new PhabricatorProject();
$table->openTransaction();
$table->beginReadLocking();

$projects = $table->loadAll();

$slug_map = array();

foreach ($projects as $project) {
  $project->setPhrictionSlug($project->getName());
  $slug = $project->getPhrictionSlug();
  if ($slug == '/') {
    $project_id = $project->getID();
    echo "Project #{$project_id} doesn't have a meaningful name...\n";
    $project->setName(trim('Unnamed Project '.$project->getName()));
  }
  $slug_map[$slug][] = $project->getID();
}


foreach ($slug_map as $slug => $similar) {
  if (count($similar) <= 1) {
    continue;
  }
  echo "Too many projects are similar to '{$slug}'...\n";

  foreach (array_slice($similar, 1, null, true) as $key => $project_id) {
    $project = $projects[$project_id];
    $old_name = $project->getName();
    $new_name = rename_project($project, $projects);

    echo "Renaming project #{$project_id} ".
         "from '{$old_name}' to '{$new_name}'.\n";
    $project->setName($new_name);
  }
}

$update = $projects;
while ($update) {
  $size = count($update);
  foreach ($update as $key => $project) {
    $id = $project->getID();
    $name = $project->getName();
    $project->setPhrictionSlug($name);
    $slug = $project->getPhrictionSlug();

    echo "Updating project #{$id} '{$name}' ({$slug})...";
    try {
      queryfx(
        $project->establishConnection('w'),
        'UPDATE %T SET name = %s, phrictionSlug = %s WHERE id = %d',
        $project->getTableName(),
        $name,
        $slug,
        $project->getID());
      unset($update[$key]);
      echo "okay.\n";
    } catch (AphrontQueryDuplicateKeyException $ex) {
      echo "failed, will retry.\n";
    }
  }
  if (count($update) == $size) {
    throw new Exception(
      "Failed to make any progress while updating projects. Schema upgrade ".
      "has failed. Go manually fix your project names to be unique (they are ".
      "probably ridiculous?) and then try again.");
  }
}

$table->endReadLocking();
$table->saveTransaction();
echo "Done.\n";


/**
 * Rename the project so that it has a unique slug, by appending (2), (3), etc.
 * to its name.
 */
function rename_project($project, $projects) {
  $suffix = 2;
  while (true) {
    $new_name = $project->getName().' ('.$suffix.')';
    $project->setPhrictionSlug($new_name);
    $new_slug = $project->getPhrictionSlug();

    $okay = true;
    foreach ($projects as $other) {
      if ($other->getID() == $project->getID()) {
        continue;
      }
      if ($other->getPhrictionSlug() == $new_slug) {
        $okay = false;
        break;
      }
    }
    if ($okay) {
      break;
    } else {
      $suffix++;
    }
  }

  return $new_name;
}
