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

  $new_view_policy = $default_view_policy;
  $new_edit_policy = $default_edit_policy;

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

      $new_view_policy = $view_policy;
      $new_edit_policy = $edit_policy;
    }
  }

  echo pht('Migrating document %d to new policy...', $id)."\n";

  queryfx(
    $conn_w,
    'UPDATE %R SET viewPolicy = %s, editPolicy = %s
      WHERE id = %d',
    $table,
    $new_view_policy,
    $new_edit_policy,
    $id);
}

echo pht('Done.')."\n";
