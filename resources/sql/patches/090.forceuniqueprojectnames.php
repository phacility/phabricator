<?php

echo pht('Ensuring project names are unique enough...')."\n";
$table = new PhabricatorProject();
$table->openTransaction();
$table->beginReadLocking();

$projects = $table->loadAll();

$slug_map = array();

foreach ($projects as $project) {
  $slug = PhabricatorSlug::normalizeProjectSlug($project->getName());

  if (!strlen($slug)) {
    $project_id = $project->getID();
    echo pht("Project #%d doesn't have a meaningful name...", $project_id)."\n";
    $project->setName(trim(pht('Unnamed Project %s', $project->getName())));
  }

  $slug_map[$slug][] = $project->getID();
}


foreach ($slug_map as $slug => $similar) {
  if (count($similar) <= 1) {
    continue;
  }
  echo pht("Too many projects are similar to '%s'...", $slug)."\n";

  foreach (array_slice($similar, 1, null, true) as $key => $project_id) {
    $project = $projects[$project_id];
    $old_name = $project->getName();
    $new_name = rename_project($project, $projects);

    echo pht(
      "Renaming project #%d from '%s' to '%s'.\n",
      $project_id,
      $old_name,
      $new_name);
    $project->setName($new_name);
  }
}

$update = $projects;
while ($update) {
  $size = count($update);
  foreach ($update as $key => $project) {
    $id = $project->getID();
    $name = $project->getName();

    $slug = PhabricatorSlug::normalizeProjectSlug($name).'/';

    echo pht("Updating project #%d '%s' (%s)... ", $id, $name, $slug);
    try {
      queryfx(
        $project->establishConnection('w'),
        'UPDATE %T SET name = %s, phrictionSlug = %s WHERE id = %d',
        $project->getTableName(),
        $name,
        $slug,
        $project->getID());
      unset($update[$key]);
      echo pht('OKAY')."\n";
    } catch (AphrontDuplicateKeyQueryException $ex) {
      echo pht('Failed, will retry.')."\n";
    }
  }
  if (count($update) == $size) {
    throw new Exception(
      pht(
        'Failed to make any progress while updating projects. Schema upgrade '.
        'has failed. Go manually fix your project names to be unique '.
        '(they are probably ridiculous?) and then try again.'));
  }
}

$table->endReadLocking();
$table->saveTransaction();
echo pht('Done.')."\n";


/**
 * Rename the project so that it has a unique slug, by appending (2), (3), etc.
 * to its name.
 */
function rename_project($project, $projects) {
  $suffix = 2;
  while (true) {
    $new_name = $project->getName().' ('.$suffix.')';

    $new_slug = PhabricatorSlug::normalizeProjectSlug($new_name).'/';

    $okay = true;
    foreach ($projects as $other) {
      if ($other->getID() == $project->getID()) {
        continue;
      }

      $other_slug = PhabricatorSlug::normalizeProjectSlug($other->getName());
      if ($other_slug == $new_slug) {
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
