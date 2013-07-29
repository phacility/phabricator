<?php

/**
 * @group pholio
 */
final class PholioMockQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $authorPHIDs;

  private $needCoverFiles;
  private $needImages;
  private $needInlineComments;
  private $needTokenCounts;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withAuthorPHIDs(array $author_phids) {
    $this->authorPHIDs = $author_phids;
    return $this;
  }

  public function needCoverFiles($need_cover_files) {
    $this->needCoverFiles = $need_cover_files;
    return $this;
  }

  public function needImages($need_images) {
    $this->needImages = $need_images;
    return $this;
  }

  public function needInlineComments($need_inline_comments) {
    $this->needInlineComments = $need_inline_comments;
    return $this;
  }

  public function needTokenCounts($need) {
    $this->needTokenCounts = $need;
    return $this;
  }

  protected function loadPage() {
    $table = new PholioMock();
    $conn_r = $table->establishConnection('r');

    $data = queryfx_all(
      $conn_r,
      'SELECT * FROM %T %Q %Q %Q',
      $table->getTableName(),
      $this->buildWhereClause($conn_r),
      $this->buildOrderClause($conn_r),
      $this->buildLimitClause($conn_r));

    $mocks = $table->loadAllFromArray($data);

    if ($mocks && $this->needImages) {
      $this->loadImages($mocks);
    }

    if ($mocks && $this->needCoverFiles) {
      $this->loadCoverFiles($mocks);
    }

    if ($mocks && $this->needTokenCounts) {
      $this->loadTokenCounts($mocks);
    }

    return $mocks;
  }

  private function buildWhereClause(AphrontDatabaseConnection $conn_r) {
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

    if ($this->authorPHIDs) {
      $where[] = qsprintf(
        $conn_r,
        'authorPHID in (%Ls)',
        $this->authorPHIDs);
    }

    return $this->formatWhereClause($where);
  }

  private function loadImages(array $mocks) {
    assert_instances_of($mocks, 'PholioMock');

    $mock_map = mpull($mocks, null, 'getID');
    $all_images = id(new PholioImageQuery())
      ->setViewer($this->getViewer())
      ->setMockCache($mock_map)
      ->withMockIDs(array_keys($mock_map))
      ->needInlineComments($this->needInlineComments)
      ->execute();

    $image_groups = mgroup($all_images, 'getMockID');

    foreach ($mocks as $mock) {
      $mock_images = idx($image_groups, $mock->getID(), array());
      $mock->attachAllImages($mock_images);
      $mock->attachImages(mfilter($mock_images, 'getIsObsolete', true));
    }
  }

  private function loadCoverFiles(array $mocks) {
    assert_instances_of($mocks, 'PholioMock');
    $cover_file_phids = mpull($mocks, 'getCoverPHID');
    $cover_files = mpull(id(new PhabricatorFile())->loadAllWhere(
      'phid IN (%Ls)',
      $cover_file_phids), null, 'getPHID');

    foreach ($mocks as $mock) {
      $file = idx($cover_files, $mock->getCoverPHID());
      if (!$file) {
        $file = PhabricatorFile::loadBuiltin($this->getViewer(), 'missing.png');
      }
      $mock->attachCoverFile($file);
    }
  }

  private function loadTokenCounts(array $mocks) {
    assert_instances_of($mocks, 'PholioMock');

    $phids = mpull($mocks, 'getPHID');
    $counts = id(new PhabricatorTokenCountQuery())
      ->withObjectPHIDs($phids)
      ->execute();

    foreach ($mocks as $mock) {
      $mock->attachTokenCount(idx($counts, $mock->getPHID(), 0));
    }
  }

}
