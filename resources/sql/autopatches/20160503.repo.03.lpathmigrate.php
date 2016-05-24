<?php

$table = new PhabricatorRepository();
$conn_w = $table->establishConnection('w');

$default_path = PhabricatorEnv::getEnvConfig('repository.default-local-path');
$default_path = rtrim($default_path, '/');

foreach (new LiskMigrationIterator($table) as $repository) {
  $local_path = $repository->getLocalPath();
  if (strlen($local_path)) {
    // Repository already has a modern, unique local path.
    continue;
  }

  $local_path = $repository->getDetail('local-path');
  if (!strlen($local_path)) {
    // Repository does not have a local path using the older format.
    continue;
  }

  $random = Filesystem::readRandomCharacters(8);

  // Try the configured path first, then a default path, then a path with some
  // random noise.
  $paths = array(
    $local_path,
    $default_path.'/'.$repository->getID().'/',
    $default_path.'/'.$repository->getID().'-'.$random.'/',
  );

  foreach ($paths as $path) {
    // Set, then get the path to normalize it.
    $repository->setLocalPath($path);
    $path = $repository->getLocalPath();

    try {
      queryfx(
        $conn_w,
        'UPDATE %T SET localPath = %s WHERE id = %d',
        $table->getTableName(),
        $path,
        $repository->getID());

      echo tsprintf(
        "%s\n",
        pht(
          'Assigned repository "%s" to local path "%s".',
          $repository->getDisplayName(),
          $path));

      break;
    } catch (AphrontDuplicateKeyQueryException $ex) {
      // Ignore, try the next one.
    }
  }
}
