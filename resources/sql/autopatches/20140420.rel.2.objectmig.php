<?php

$pull_table = new ReleephRequest();
$table_name = $pull_table->getTableName();
$conn_w = $pull_table->establishConnection('w');

echo pht('Setting object PHIDs for requests...')."\n";
foreach (new LiskMigrationIterator($pull_table) as $pull) {
  $id = $pull->getID();

  echo pht('Migrating pull request %d...', $id)."\n";
  if ($pull->getRequestedObjectPHID()) {
    // We already have a valid PHID, so skip this request.
    continue;
  }

  $commit_phids = $pull->getCommitPHIDs();
  if (count($commit_phids) != 1) {
    // At the time this migration was written, all requests had exactly one
    // commit. If a request has more than one, we don't have the information
    // we need to process it.
    continue;
  }

  $commit_phid = head($commit_phids);

  $revision_phids = PhabricatorEdgeQuery::loadDestinationPHIDs(
    $commit_phid,
    DiffusionCommitHasRevisionEdgeType::EDGECONST);

  if ($revision_phids) {
    $object_phid = head($revision_phids);
  } else {
    $object_phid = $commit_phid;
  }

  queryfx(
    $conn_w,
    'UPDATE %T SET requestedObjectPHID = %s WHERE id = %d',
    $table_name,
    $object_phid,
    $id);
}

echo pht('Done.')."\n";
