<?php

final class PhabricatorAuditTransactionView
  extends PhabricatorApplicationTransactionView {

  private $pathMap;

  public function setPathMap(array $path_map) {
    $this->pathMap = $path_map;
    return $this;
  }

  public function getPathMap() {
    return $this->pathMap;
  }

  // TODO: This shares a lot of code with Differential and Pholio and should
  // probably be merged up.

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
      case PhabricatorAuditActionConstants::INLINE:
        break;
      default:
        return false;
    }

    switch ($v->getTransactionType()) {
      case PhabricatorAuditActionConstants::INLINE:
        return true;
    }

    return parent::shouldGroupTransactions($u, $v);
  }

  protected function renderTransactionContent(
    PhabricatorApplicationTransaction $xaction) {

    $out = array();

    $type_inline = PhabricatorAuditActionConstants::INLINE;

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
        case PhabricatorAuditActionConstants::INLINE:
          $inlines[] = $xaction;
          break;
        default:
          throw new Exception(pht('Unknown grouped transaction type!'));
      }
    }

    if ($inlines) {

      // TODO: This should do something similar to sortAndGroupInlines() to get
      // a stable ordering.

      $inlines_by_path = array();
      foreach ($inlines as $key => $inline) {
        $comment = $inline->getComment();
        if (!$comment) {
          // TODO: Migrate these away? They probably do not exist on normal
          // non-development installs.
          unset($inlines[$key]);
          continue;
        }
        $path_id = $comment->getPathID();
        $inlines_by_path[$path_id][] = $inline;
      }

      $inline_view = new PhabricatorInlineSummaryView();
      foreach ($inlines_by_path as $path_id => $group) {
        $path = idx($this->pathMap, $path_id);
        if ($path === null) {
          continue;
        }

        $items = array();
        foreach ($group as $inline) {
          $comment = $inline->getComment();
          $item = array(
            'id' => $comment->getID(),
            'line' => $comment->getLineNumber(),
            'length' => $comment->getLineLength(),
            'content' => parent::renderTransactionContent($inline),
          );
          $items[] = $item;
        }
        $inline_view->addCommentGroup($path, $items);
      }

      $out[] = $inline_view;
    }

    return $out;
  }

}
