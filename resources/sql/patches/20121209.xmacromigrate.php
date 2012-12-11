<?php

echo "Giving image macros PHIDs";
foreach (new LiskMigrationIterator(new PhabricatorFileImageMacro()) as $macro) {
  if ($macro->getPHID()) {
    continue;
  }

  echo ".";

  queryfx(
    $macro->establishConnection('r'),
    'UPDATE %T SET phid = %s WHERE id = %d',
    $macro->getTableName(),
    $macro->generatePHID(),
    $macro->getID());
}
echo "\nDone.\n";
