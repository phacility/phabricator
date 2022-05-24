<?php

final class PholioImageQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $mockPHIDs;
  private $mocks;

  private $needInlineComments;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withMocks(array $mocks) {
    assert_instances_of($mocks, 'PholioMock');

    $mocks = mpull($mocks, null, 'getPHID');
    $this->mocks = $mocks;
    $this->mockPHIDs = array_keys($mocks);

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

  public function newResultObject() {
    return new PholioImage();
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

    $mock_phids = array();
    foreach ($images as $image) {
      if (!$image->hasMock()) {
        continue;
      }

      $mock_phids[] = $image->getMockPHID();
    }

    if ($mock_phids) {
      if ($this->mocks) {
        $mocks = $this->mocks;
      } else {
        $mocks = id(new PholioMockQuery())
          ->setViewer($this->getViewer())
          ->withPHIDs($mock_phids)
          ->execute();
      }

      $mocks = mpull($mocks, null, 'getPHID');

      foreach ($images as $key => $image) {
        if (!$image->hasMock()) {
          continue;
        }

        $mock = idx($mocks, $image->getMockPHID());
        if (!$mock) {
          unset($images[$key]);
          $this->didRejectResult($image);
          continue;
        }

        $image->attachMock($mock);
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
