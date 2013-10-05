<?php

$table = new DifferentialRevision();
$conn_w = $table->establishConnection('w');

// NOTE: We migrate by revision because the relationship table doesn't have
// an "id" column.

foreach (new LiskMigrationIterator($table) as $revision) {
  $revision_id = $revision->getID();
  $revision_phid = $revision->getPHID();

  echo "Migrating reviewers for D{$revision_id}...\n";

  $reviewer_phids = queryfx_all(
    $conn_w,
    'SELECT objectPHID FROM %T WHERE revisionID = %d
      AND relation = %s ORDER BY sequence',
    'differential_relationship',
    $revision_id,
    'revw');
  $reviewer_phids = ipull($reviewer_phids, 'objectPHID');

  if (!$reviewer_phids) {
    continue;
  }

  $editor = id(new PhabricatorEdgeEditor())
    ->setActor(PhabricatorUser::getOmnipotentUser());

  foreach ($reviewer_phids as $dst) {
    $editor->addEdge(
      $revision_phid,
      PhabricatorEdgeConfig::TYPE_DREV_HAS_REVIEWER,
      $dst,
      array(
        'data' => array(
          'status' => DifferentialReviewerStatus::STATUS_ADDED,
        ),
      ));
  }

  $editor->save();
}

echo "Done.\n";
