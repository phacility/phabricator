<?php

echo pht('Updating users...')."\n";

foreach (new LiskMigrationIterator(new PhabricatorUser()) as $user) {
  $id = $user->getID();
  echo pht('Updating %d...', $id)."\n";

  if (strlen($user->getAccountSecret())) {
    continue;
  }

  queryfx(
    $user->establishConnection('w'),
    'UPDATE %T SET accountSecret = %s WHERE id = %d',
    $user->getTableName(),
    Filesystem::readRandomCharacters(64),
    $id);
}

echo pht('Done.')."\n";
