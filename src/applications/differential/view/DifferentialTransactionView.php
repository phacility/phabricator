<?php

final class DifferentialTransactionView
  extends PhabricatorApplicationTransactionView {

  private $changesets;

  public function setChangesets(array $changesets) {
    assert_instances_of($changesets, 'DifferentialChangeset');
    $this->changesets = $changesets;
    return $this;
  }

  public function getChangesets() {
    return $this->changesets;
  }

  // TODO: There's a whole lot of code duplication between this and
  // PholioTransactionView to handle inlines. Merge this into the core? Some of
  // it can probably be shared, while other parts are trickier.

  protected function shouldGroupTransactions(
    PhabricatorApplicationTransaction $u,
    PhabricatorApplicationTransaction $v) {

    if ($u->getAuthorPHID() != $v->getAuthorPHID()) {
      // Don't group transactions by different authors.
      return false;
    }

    if (($v->getDateCreated() - $u->getDateCreated()) > 60) {
      // Don't group if transactions that happened more than 60s apart.
      return false;
    }

    switch ($u->getTransactionType()) {
      case PhabricatorTransactions::TYPE_COMMENT:
      case DifferentialTransaction::TYPE_INLINE:
        break;
      default:
        return false;
    }

    switch ($v->getTransactionType()) {
      case DifferentialTransaction::TYPE_INLINE:
        return true;
    }

    return parent::shouldGroupTransactions($u, $v);
  }

  protected function renderTransactionContent(
    PhabricatorApplicationTransaction $xaction) {

    $out = array();

    $type_inline = DifferentialTransaction::TYPE_INLINE;

    $group = $xaction->getTransactionGroup();
    if ($xaction->getTransactionType() == $type_inline) {
      array_unshift($group, $xaction);
    } else {
      $out[] = parent::renderTransactionContent($xaction);
    }

    if (!$group) {
      return $out;
    }

    $inlines = array();
    foreach ($group as $xaction) {
      switch ($xaction->getTransactionType()) {
        case DifferentialTransaction::TYPE_INLINE:
          $inlines[] = $xaction;
          break;
        default:
          throw new Exception("Unknown grouped transaction type!");
      }
    }

    if ($inlines) {
      $inline_view = new PhabricatorInlineSummaryView();

      $changesets = $this->getChangesets();
      $changesets = mpull($changesets, null, 'getID');

      // Group the changesets by file and reorder them by display order.
      $inline_groups = array();
      foreach ($inlines as $inline) {
        $inline_groups[$inline->getComment()->getChangesetID()][] = $inline;
      }

      $changsets = msort($changesets, 'getFilename');
      $inline_groups = array_select_keys(
        $inline_groups,
        array_keys($changesets));

      foreach ($inline_groups as $changeset_id => $group) {
        $group = msort($group, 'getLineNumber');

        $changeset = $changesets[$changeset_id];
        $items = array();
        foreach ($group as $inline) {
          $comment = $inline->getComment();
          $item = array(
            'id' => $comment->getID(),
            'line' => $comment->getLineNumber(),
            'length' => $comment->getLineLength(),
            'content' => parent::renderTransactionContent($inline),
          );

          // TODO: Fix the where/href stuff for nonlocal inlines.

          $items[] = $item;
        }
        $inline_view->addCommentGroup(
          $changeset->getFilename(),
          $items);
      }

      $out[] = $inline_view;
    }

    return $out;
  }

}
