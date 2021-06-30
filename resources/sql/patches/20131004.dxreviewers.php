<?php

$table = new DifferentialRevision();
$conn_w = $table->establishConnection('w');

// NOTE: We migrate by revision because the relationship table doesn't have
// an "id" column.

foreach (new LiskMigrationIterator($table) as $revision) {
  $revision_id = $revision->getID();
  $revision_phid = $revision->getPHID();

  echo pht('Migrating reviewers for %s...', "D{$revision_id}")."\n";

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

  $editor = new PhabricatorEdgeEditor();
  foreach ($reviewer_phids as $dst) {
    if (phid_get_type($dst) == PhabricatorPHIDConstants::PHID_TYPE_UNKNOWN) {
      // At least one old install ran into some issues here. Skip the row if we
      // can't figure out what the destination PHID is.
      continue;
    }

    $editor->addEdge(
      $revision_phid,
      DifferentialRevisionHasReviewerEdgeType::EDGECONST,
      $dst,
      array(
        'data' => array(
          'status' => DifferentialReviewerStatus::STATUS_ADDED,
        ),
      ));
  }

  $editor->save();
}

echo pht('Done.')."\n";
