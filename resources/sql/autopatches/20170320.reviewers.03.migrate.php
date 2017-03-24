<?php

$table = new DifferentialRevision();
$diff_table = new DifferentialDiff();
$reviewer_table = new DifferentialReviewer();

$table_name = PhabricatorEdgeConfig::TABLE_NAME_EDGE;
$data_name = PhabricatorEdgeConfig::TABLE_NAME_EDGEDATA;

$conn = $table->establishConnection('w');

// Previously "DifferentialRevisionHasReviewerEdgeType::EDGECONST".
$edge_type = 35;

// NOTE: We can't use normal migration iterators for edges because they don't
// have an "id" column. For now, try just loading the whole result set: the
// actual size of the rows is small. If we run into issues, we could write an
// EdgeIterator.
$every_edge = queryfx_all(
  $conn,
  'SELECT * FROM %T edge LEFT JOIN %T data ON edge.dataID = data.id
    WHERE edge.type = %d',
  $table_name,
  $data_name,
  $edge_type);

foreach ($every_edge as $edge) {
  if ($edge['type'] != $edge_type) {
    // Ignore edges which aren't "reviewers", like subscribers.
    continue;
  }

  try {
    $data = phutil_json_decode($edge['data']);
    $data = idx($data, 'data');
  } catch (Exception $ex) {
    // Just ignore any kind of issue with the edge data, we'll use a default
    // below.
    $data = null;
  }

  if (!$data) {
    $data = array(
      'status' => 'added',
    );
  }

  $status = idx($data, 'status');

  $diff_phid = null;

  // NOTE: At one point, the code to populate "diffID" worked correctly, but
  // it seems to have later been broken. Salvage it if we can, and look up
  // the corresponding diff PHID.
  $diff_id = idx($data, 'diffID');
  if ($diff_id) {
    $row = queryfx_one(
      $conn,
      'SELECT phid FROM %T WHERE id = %d',
      $diff_table->getTableName(),
      $diff_id);
    if ($row) {
      $diff_phid = $row['phid'];
    }
  }

  if (!$diff_phid) {
    // If the status is "accepted" or "rejected", look up the current diff
    // PHID so we can distinguish between "accepted" and "accepted older".
    switch ($status) {
      case 'accepted':
      case 'rejected':
      case 'commented':
        $row = queryfx_one(
          $conn,
          'SELECT diff.phid FROM %T diff JOIN %T revision
            ON diff.revisionID = revision.id
            WHERE revision.phid = %s
            ORDER BY diff.id DESC LIMIT 1',
          $diff_table->getTableName(),
          $table->getTableName(),
          $edge['src']);
        if ($row) {
          $diff_phid = $row['phid'];
        }
        break;
    }
  }

  // We now represent some states (like "Commented" and "Accepted Older") as
  // a primary state plus an extra flag, instead of making "Commented" a
  // primary state. Map old states to new states and flags.

  if ($status == 'commented') {
    $status = 'added';
    $comment_phid = $diff_phid;
    $action_phid = null;
  } else {
    $comment_phid = null;
    $action_phid = $diff_phid;
  }

  if ($status == 'accepted-older') {
    $status = 'accepted';
  }

  if ($status == 'rejected-older') {
    $status = 'rejected';
  }

  queryfx(
    $conn,
    'INSERT INTO %T (revisionPHID, reviewerPHID, reviewerStatus,
      lastActionDiffPHID, lastCommentDiffPHID, dateCreated, dateModified)
      VALUES (%s, %s, %s, %ns, %ns, %d, %d)
      ON DUPLICATE KEY UPDATE dateCreated = VALUES(dateCreated)',
    $reviewer_table->getTableName(),
    $edge['src'],
    $edge['dst'],
    $status,
    $action_phid,
    $comment_phid,
    $edge['dateCreated'],
    $edge['dateCreated']);
}
