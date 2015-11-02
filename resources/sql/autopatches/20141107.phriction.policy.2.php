<?php

$table = new PhrictionDocument();
$conn_w = $table->establishConnection('w');

echo pht('Populating Phriction policies.')."\n";

$default_view_policy = PhabricatorPolicies::POLICY_USER;
$default_edit_policy = PhabricatorPolicies::POLICY_USER;

foreach (new LiskMigrationIterator($table) as $doc) {
  $id = $doc->getID();

  if ($doc->getViewPolicy() && $doc->getEditPolicy()) {
    echo pht('Skipping document %d; already has policy set.', $id)."\n";
    continue;
  }

  // If this was previously a magical project wiki page (under projects/, but
  // not projects/ itself) we need to apply the project policies. Otherwise,
  // apply the default policies.
  $slug = $doc->getSlug();
  $slug = PhabricatorSlug::normalize($slug);
  $prefix = 'projects/';
  if (($slug != $prefix) && (strncmp($slug, $prefix, strlen($prefix)) === 0)) {
    $parts = explode('/', $slug);

    $project_slug = $parts[1];
    $project_slug = PhabricatorSlug::normalizeProjectSlug($project_slug);

    $project_slugs = array($project_slug);
    $project = id(new PhabricatorProjectQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withSlugs($project_slugs)
      ->executeOne();

    if ($project) {

      $view_policy = nonempty($project->getViewPolicy(), $default_view_policy);
      $edit_policy = nonempty($project->getEditPolicy(), $default_edit_policy);

      $project_name = $project->getName();
      echo pht(
        "Migrating document %d to project policy %s...\n",
        $id,
        $project_name);
      $doc->setViewPolicy($view_policy);
      $doc->setEditPolicy($edit_policy);
      $doc->save();
      continue;
    }
  }

  echo pht('Migrating document %d to default install policy...', $id)."\n";
  $doc->setViewPolicy($default_view_policy);
  $doc->setEditPolicy($default_edit_policy);
  $doc->save();
}

echo pht('Done.')."\n";
