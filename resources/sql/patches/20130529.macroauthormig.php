<?php

echo pht('Migrating macro authors...')."\n";
foreach (new LiskMigrationIterator(new PhabricatorFileImageMacro()) as $macro) {
  echo pht('Macro #%d', $macro->getID())."\n";

  if ($macro->getAuthorPHID()) {
    // Already have an author; skip it.
    continue;
  }

  if (!$macro->getFilePHID()) {
    // No valid file; skip it.
    continue;
  }

  $file = id(new PhabricatorFile())->loadOneWhere(
    'phid = %s',
    $macro->getFilePHID());

  if (!$file) {
    // Couldn't load the file; skip it.
    continue;
  }

  if (!$file->getAuthorPHID()) {
    // File has no author; skip it.
    continue;
  }

  queryfx(
    $macro->establishConnection('w'),
    'UPDATE %T SET authorPHID = %s WHERE id = %d',
    $macro->getTableName(),
    $file->getAuthorPHID(),
    $macro->getID());
}

echo pht('Done.')."\n";
