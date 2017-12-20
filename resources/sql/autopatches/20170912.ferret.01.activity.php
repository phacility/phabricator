<?php

// Advise installs to perform a reindex in order to rebuild the Ferret engine
// indexes.

// If the install is completely empty with no user accounts, don't require
// a rebuild. In particular, this happens when rebuilding the quickstart file.
$users = id(new PhabricatorUser())->loadAllWhere('1 = 1 LIMIT 1');
if (!$users) {
  return;
}

try {
  id(new PhabricatorConfigManualActivity())
    ->setActivityType(PhabricatorConfigManualActivity::TYPE_REINDEX)
    ->save();
} catch (AphrontDuplicateKeyQueryException $ex) {
  // If we've already noted that this activity is required, just move on.
}
