<?php

echo pht('Stripping remotes from repository default branches...')."\n";

$table = new PhabricatorRepository();
$table->openTransaction();
$conn_w = $table->establishConnection('w');

$repos = queryfx_all(
  $conn_w,
  'SELECT id, name, details FROM %T WHERE versionControlSystem = %s FOR UPDATE',
  $table->getTableName(),
  'git');

foreach ($repos as $repo) {
  $details = json_decode($repo['details'], true);

  $old = idx($details, 'default-branch', '');
  if (strpos($old, '/') === false) {
    continue;
  }

  $parts = explode('/', $old);
  $parts = array_filter($parts);
  $new = end($parts);

  $details['default-branch'] = $new;
  $new_details = json_encode($details);

  $id = $repo['id'];
  $name = $repo['name'];

  echo pht(
    "Updating default branch for repository #%d '%s' from ".
    "'%s' to '%s' to remove the explicit remote.\n",
    $id,
    $name,
    $old,
    $new);
  queryfx(
    $conn_w,
    'UPDATE %T SET details = %s WHERE id = %d',
    $table->getTableName(),
    $new_details,
    $id);
}

$table->saveTransaction();
echo pht('Done.')."\n";
