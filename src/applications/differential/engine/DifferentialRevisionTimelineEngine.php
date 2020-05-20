<?php

final class DifferentialRevisionTimelineEngine
  extends PhabricatorTimelineEngine {

  protected function newTimelineView() {
    $viewer = $this->getViewer();
    $xactions = $this->getTransactions();
    $revision = $this->getObject();

    $view_data = $this->getViewData();
    if (!$view_data) {
      $view_data = array();
    }

    $left = idx($view_data, 'left');
    $right = idx($view_data, 'right');

    $diffs = id(new DifferentialDiffQuery())
      ->setViewer($viewer)
      ->withIDs(array($left, $right))
      ->execute();
    $diffs = mpull($diffs, null, 'getID');
    $left_diff = $diffs[$left];
    $right_diff = $diffs[$right];

    $old_ids = idx($view_data, 'old');
    $new_ids = idx($view_data, 'new');
    $old_ids = array_filter(explode(',', $old_ids));
    $new_ids = array_filter(explode(',', $new_ids));

    $type_inline = DifferentialTransaction::TYPE_INLINE;
    $changeset_ids = array_merge($old_ids, $new_ids);
    $inlines = array();
    foreach ($xactions as $xaction) {
      if ($xaction->getTransactionType() == $type_inline) {
        $inlines[] = $xaction->getComment();
        $changeset_ids[] = $xaction->getComment()->getChangesetID();
      }
    }

    if ($changeset_ids) {
      $changesets = id(new DifferentialChangesetQuery())
        ->setViewer($viewer)
        ->withIDs($changeset_ids)
        ->execute();
      $changesets = mpull($changesets, null, 'getID');
    } else {
      $changesets = array();
    }

    foreach ($inlines as $key => $inline) {
      $inlines[$key] = $inline->newInlineCommentObject();
    }

    // NOTE: This is a bit sketchy: this method adjusts the inlines as a
    // side effect, which means it will ultimately adjust the transaction
    // comments and affect timeline rendering.

    $old = array_select_keys($changesets, $old_ids);
    $new = array_select_keys($changesets, $new_ids);
    id(new PhabricatorInlineCommentAdjustmentEngine())
      ->setViewer($viewer)
      ->setRevision($revision)
      ->setOldChangesets($old)
      ->setNewChangesets($new)
      ->setInlines($inlines)
      ->execute();

    return id(new DifferentialTransactionView())
      ->setViewData($view_data)
      ->setChangesets($changesets)
      ->setRevision($revision)
      ->setLeftDiff($left_diff)
      ->setRightDiff($right_diff);
  }

}
