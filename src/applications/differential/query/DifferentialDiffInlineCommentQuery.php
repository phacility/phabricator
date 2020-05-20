<?php

final class DifferentialDiffInlineCommentQuery
  extends PhabricatorDiffInlineCommentQuery {

  private $revisionPHIDs;

  protected function newApplicationTransactionCommentTemplate() {
    return new DifferentialTransactionComment();
  }

  public function withRevisionPHIDs(array $phids) {
    $this->revisionPHIDs = $phids;
    return $this;
  }

  public function withObjectPHIDs(array $phids) {
    return $this->withRevisionPHIDs($phids);
  }

  protected function buildInlineCommentWhereClauseParts(
    AphrontDatabaseConnection $conn) {
    $where = array();
    $alias = $this->getPrimaryTableAlias();

    $where[] = qsprintf(
      $conn,
      'changesetID IS NOT NULL');

    return $where;
  }

  protected function buildWhereClauseParts(AphrontDatabaseConnection $conn) {
    $where = parent::buildWhereClauseParts($conn);
    $alias = $this->getPrimaryTableAlias();

    if ($this->revisionPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        '%T.revisionPHID IN (%Ls)',
        $alias,
        $this->revisionPHIDs);
    }

    return $where;
  }

  protected function loadHiddenCommentIDs(
    $viewer_phid,
    array $comments) {

    $table = new DifferentialHiddenComment();
    $conn = $table->establishConnection('r');

    $rows = queryfx_all(
      $conn,
      'SELECT commentID FROM %R
        WHERE userPHID = %s
        AND commentID IN (%Ld)',
      $table,
      $viewer_phid,
      mpull($comments, 'getID'));

    $id_map = ipull($rows, 'commentID');
    $id_map = array_fuse($id_map);

    return $id_map;
  }

  protected function newInlineContextMap(array $inlines) {
    $viewer = $this->getViewer();

    $map = array();

    foreach ($inlines as $key => $inline) {
      $changeset = id(new DifferentialChangesetQuery())
        ->setViewer($viewer)
        ->withIDs(array($inline->getChangesetID()))
        ->needHunks(true)
        ->executeOne();
      if (!$changeset) {
        continue;
      }

      $hunks = $changeset->getHunks();

      $is_simple =
        (count($hunks) === 1) &&
        ((int)head($hunks)->getOldOffset() <= 1) &&
        ((int)head($hunks)->getNewOffset() <= 1);

      if (!$is_simple) {
        continue;
      }

      if ($inline->getIsNewFile()) {
        $vector = $changeset->getNewStatePathVector();
        $filename = last($vector);
        $corpus = $changeset->makeNewFile();
      } else {
        $vector = $changeset->getOldStatePathVector();
        $filename = last($vector);
        $corpus = $changeset->makeOldFile();
      }

      $corpus = phutil_split_lines($corpus);

      // Adjust the line number into a 0-based offset.
      $offset = $inline->getLineNumber();
      $offset = $offset - 1;

      // Adjust the inclusive range length into a row count.
      $length = $inline->getLineLength();
      $length = $length + 1;

      $head_min = max(0, $offset - 3);
      $head_max = $offset;
      $head_len = $head_max - $head_min;

      if ($head_len) {
        $head = array_slice($corpus, $head_min, $head_len, true);
        $head = $this->simplifyContext($head, true);
      } else {
        $head = array();
      }

      $body = array_slice($corpus, $offset, $length, true);

      $tail = array_slice($corpus, $offset + $length, 3, true);
      $tail = $this->simplifyContext($tail, false);

      $context = id(new PhabricatorDiffInlineCommentContext())
        ->setFilename($filename)
        ->setHeadLines($head)
        ->setBodyLines($body)
        ->setTailLines($tail);

      $map[$key] = $context;
    }

    return $map;
  }

}
