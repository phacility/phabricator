<?php

final class PholioImageQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $mockIDs;
  private $obsolete;

  private $needInlineComments;
  private $mockCache = array();

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withMockIDs(array $mock_ids) {
    $this->mockIDs = $mock_ids;
    return $this;
  }

  public function withObsolete($obsolete) {
    $this->obsolete = $obsolete;
    return $this;
  }

  public function needInlineComments($need_inline_comments) {
    $this->needInlineComments = $need_inline_comments;
    return $this;
  }

  public function setMockCache($mock_cache) {
    $this->mockCache = $mock_cache;
    return $this;
  }
  public function getMockCache() {
    return $this->mockCache;
  }

  protected function loadPage() {
    $table = new PholioImage();
    $conn_r = $table->establishConnection('r');

    $data = queryfx_all(
      $conn_r,
      'SELECT * FROM %T %Q %Q %Q',
      $table->getTableName(),
      $this->buildWhereClause($conn_r),
      $this->buildOrderClause($conn_r),
      $this->buildLimitClause($conn_r));

    $images = $table->loadAllFromArray($data);

    return $images;
  }

  protected function buildWhereClause(AphrontDatabaseConnection $conn_r) {
    $where = array();

    $where[] = $this->buildPagingClause($conn_r);

    if ($this->ids) {
      $where[] = qsprintf(
        $conn_r,
        'id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids) {
      $where[] = qsprintf(
        $conn_r,
        'phid IN (%Ls)',
        $this->phids);
    }

    if ($this->mockIDs) {
      $where[] = qsprintf(
        $conn_r,
        'mockID IN (%Ld)',
        $this->mockIDs);
    }

    if ($this->obsolete !== null) {
      $where[] = qsprintf(
        $conn_r,
        'isObsolete = %d',
        $this->obsolete);
    }

    return $this->formatWhereClause($where);
  }

  protected function willFilterPage(array $images) {
    assert_instances_of($images, 'PholioImage');

    if ($this->getMockCache()) {
      $mocks = $this->getMockCache();
    } else {
      $mock_ids = mpull($images, 'getMockID');
      // DO NOT set needImages to true; recursion results!
      $mocks = id(new PholioMockQuery())
        ->setViewer($this->getViewer())
        ->withIDs($mock_ids)
        ->execute();
      $mocks = mpull($mocks, null, 'getID');
    }
    foreach ($images as $index => $image) {
      $mock = idx($mocks, $image->getMockID());
      if ($mock) {
        $image->attachMock($mock);
      } else {
        // mock is missing or we can't see it
        unset($images[$index]);
      }
    }

    return $images;
  }

  protected function didFilterPage(array $images) {
    assert_instances_of($images, 'PholioImage');

    $file_phids = mpull($images, 'getFilePHID');

    $all_files = id(new PhabricatorFileQuery())
      ->setParentQuery($this)
      ->setViewer($this->getViewer())
      ->withPHIDs($file_phids)
      ->execute();
    $all_files = mpull($all_files, null, 'getPHID');

    if ($this->needInlineComments) {
      // Only load inline comments the viewer has permission to see.
      $all_inline_comments = id(new PholioTransactionComment())->loadAllWhere(
        'imageID IN (%Ld)
          AND (transactionPHID IS NOT NULL OR authorPHID = %s)',
        mpull($images, 'getID'),
        $this->getViewer()->getPHID());
      $all_inline_comments = mgroup($all_inline_comments, 'getImageID');
    }

    foreach ($images as $image) {
      $file = idx($all_files, $image->getFilePHID());
      if (!$file) {
        $file = PhabricatorFile::loadBuiltin($this->getViewer(), 'missing.png');
      }
      $image->attachFile($file);
      if ($this->needInlineComments) {
        $inlines = idx($all_inline_comments, $image->getID(), array());
        $image->attachInlineComments($inlines);
      }
    }

    return $images;
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorPholioApplication';
  }

}
