<?php

$table = new PhabricatorRepository();
$conn_w = $table->establishConnection('w');

foreach (new LiskMigrationIterator($table) as $repository) {
  $slug = $repository->getRepositorySlug();

  if ($slug !== null) {
    continue;
  }

  $clone_name = $repository->getDetail('clone-name');

  if (!strlen($clone_name)) {
    continue;
  }

  if (!PhabricatorRepository::isValidRepositorySlug($clone_name)) {
    echo tsprintf(
      "%s\n",
      pht(
        'Repository "%s" has a "Clone/Checkout As" name which is no longer '.
        'valid ("%s"). You can edit the repository to give it a new, valid '.
        'short name.',
        $repository->getDisplayName(),
        $clone_name));
    continue;
  }

  try {
    queryfx(
      $conn_w,
      'UPDATE %T SET repositorySlug = %s WHERE id = %d',
      $table->getTableName(),
      $clone_name,
      $repository->getID());
  } catch (AphrontDuplicateKeyQueryException $ex) {
    echo tsprintf(
      "%s\n",
      pht(
        'Repository "%s" has a duplicate "Clone/Checkout As" name ("%s"). '.
        'Each name must now be unique. You can edit the repository to give '.
        'it a new, unique short name.',
        $repository->getDisplayName(),
        $clone_name));
  }

}
