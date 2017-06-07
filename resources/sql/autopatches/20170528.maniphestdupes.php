<?php

$table = new ManiphestTransaction();
$add_edges = array();

foreach (new LiskMigrationIterator($table) as $txn) {
  $txn_type = $txn->getTransactionType();

  if ($txn_type == 'mergedinto') {
    // dupe handling as implemented in D10427, which creates a specific txn
    $add_edges[] = array(
      'src' => $txn->getObjectPHID(),
      'dst' => $txn->getNewValue(),
    );
  } else if ($txn_type == 'status' && $txn->getNewValue() == 'duplicate') {
    // dupe handling as originally implemented, which just changes the status
    // and adds a comment
    $src_phid = $txn->getObjectPHID();

    // get all the comment transactions associated with this task
    $viewer = PhabricatorUser::getOmnipotentUser();
    $comment_txns = id(new ManiphestTransactionQuery())
      ->setViewer($viewer)
      ->withObjectPHIDs(array($src_phid))
      ->needComments(true)
      ->execute();

    // check each comment, looking for the "Merged Into" message
    foreach ($comment_txns as $comment_txn) {
      if ($comment_txn->hasComment()) {
        $comment = $comment_txn->getComment()->getContent();
        $pattern = '/^\xE2\x9C\x98 Merged into T(\d+)\.$/';
        $matches = array();

        if (preg_match($pattern, $comment, $matches)) {
          $dst_task = id(new ManiphestTaskQuery())
            ->setViewer($viewer)
            ->withIDs(array($matches[1]))
            ->executeOne();

          if ($dst_task) {
            $dst_phid = $dst_task->getPHID();
            $add_edges[] = array(
              'src' => $src_phid,
              'dst' => $dst_phid,
            );
          }
        }
      }
    }
  }
}

if ($add_edges) {
  foreach ($add_edges as $edge) {
    $src_phid = $edge['src'];
    $dst_phid = $edge['dst'];

    $type = ManiphestTaskIsDuplicateOfTaskEdgeType::EDGECONST;
    try {
      $editor = id(new PhabricatorEdgeEditor())
        ->addEdge($src_phid, $type, $dst_phid)
        ->save();
    } catch (PhabricatorEdgeCycleException $ex) {
      // Some earlier or later merge made this invalid, just skip it.
    }
  }
}
