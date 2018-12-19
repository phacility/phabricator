<?php

final class PholioImageQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $mockPHIDs;

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

  public function withMockPHIDs(array $mock_phids) {
    $this->mockPHIDs = $mock_phids;
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

  public function newResultObject() {
    return new PholioImage();
  }

  protected function loadPage() {
    return $this->loadStandardPage($this->newResultObject());
  }

  protected function buildWhereClauseParts(AphrontDatabaseConnection $conn) {
    $where = parent::buildWhereClauseParts($conn);

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn,
        'id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids !== null) {
      $where[] = qsprintf(
        $conn,
        'phid IN (%Ls)',
        $this->phids);
    }

    if ($this->mockPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'mockPHID IN (%Ls)',
        $this->mockPHIDs);
    }

    return $where;
  }

  protected function willFilterPage(array $images) {
    assert_instances_of($images, 'PholioImage');

    if ($this->getMockCache()) {
      $mocks = $this->getMockCache();
    } else {
      $mock_phids = mpull($images, 'getMockPHID');

      // DO NOT set needImages to true; recursion results!
      $mocks = id(new PholioMockQuery())
        ->setViewer($this->getViewer())
        ->withPHIDs($mock_phids)
        ->execute();
      $mocks = mpull($mocks, null, 'getPHID');
    }

    foreach ($images as $index => $image) {
      $mock = idx($mocks, $image->getMockPHID());
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
