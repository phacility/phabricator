<?php

echo pht('Migrating Differential unsubscribed users to edges...')."\n";
$table = new DifferentialRevision();
$table->openTransaction();

// We couldn't use new LiskMigrationIterator($table) because the $unsubscribed
// property gets deleted.
$revs = queryfx_all(
  $table->establishConnection('w'),
  'SELECT id, phid, unsubscribed FROM differential_revision');

foreach ($revs as $rev) {
  echo '.';

  $unsubscribed = json_decode($rev['unsubscribed']);
  if (!$unsubscribed) {
    continue;
  }

  $editor = new PhabricatorEdgeEditor();
  foreach ($unsubscribed as $user_phid => $_) {
    $editor->addEdge(
      $rev['phid'],
      PhabricatorObjectHasUnsubscriberEdgeType::EDGECONST ,
      $user_phid);
  }
  $editor->save();
}

$table->saveTransaction();
echo pht('Done.')."\n";
