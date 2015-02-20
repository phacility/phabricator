<?php

final class DifferentialTransactionView
  extends PhabricatorApplicationTransactionView {

  private $changesets;
  private $revision;
  private $rightDiff;
  private $leftDiff;

  public function setLeftDiff(DifferentialDiff $left_diff) {
    $this->leftDiff = $left_diff;
    return $this;
  }

  public function getLeftDiff() {
    return $this->leftDiff;
  }

  public function setRightDiff(DifferentialDiff $right_diff) {
    $this->rightDiff = $right_diff;
    return $this;
  }

  public function getRightDiff() {
    return $this->rightDiff;
  }

  public function setRevision(DifferentialRevision $revision) {
    $this->revision = $revision;
    return $this;
  }

  public function getRevision() {
    return $this->revision;
  }

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
          throw new Exception('Unknown grouped transaction type!');
      }
    }

    if ($inlines) {
      $inline_view = new PhabricatorInlineSummaryView();

      $changesets = $this->getChangesets();

      $inline_groups = DifferentialTransactionComment::sortAndGroupInlines(
        $inlines,
        $changesets);
      foreach ($inline_groups as $changeset_id => $group) {
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

          $changeset_diff_id = $changeset->getDiffID();
          if ($comment->getIsNewFile()) {
            $visible_diff_id = $this->getRightDiff()->getID();
          } else {
            $visible_diff_id = $this->getLeftDiff()->getID();
          }

          // TODO: We still get one edge case wrong here, when we have a
          // versus diff and the file didn't exist in the old version. The
          // comment is visible because we show the left side of the target
          // diff when there's no corresponding file in the versus diff, but
          // we incorrectly link it off-page.

          $is_visible = ($changeset_diff_id == $visible_diff_id);
          if (!$is_visible) {
            $revision_id = $this->getRevision()->getID();
            $comment_id = $comment->getID();
            $item['href'] =
              '/D'.$revision_id.
              '?id='.$changeset_diff_id.
              '#inline-'.$comment_id;
            $item['where'] = pht('(On Diff #%d)', $changeset_diff_id);
          }

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
