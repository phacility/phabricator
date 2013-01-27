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

  public function loadPage() {
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

  public function needCoverFiles($need_cover_files) {
    $this->needCoverFiles = $need_cover_files;
    return $this;
  }

  public function needImages($need_images) {
    $this->needImages = $need_images;
    return $this;
  }

  public function loadImages(array $mocks) {
    assert_instances_of($mocks, 'PholioMock');

    $mock_ids = mpull($mocks, 'getID');
    $all_images = id(new PholioImage())->loadAllWhere(
      'mockID IN (%Ld)',
      $mock_ids);

    $file_phids = mpull($all_images, 'getFilePHID');
    $all_files = mpull(id(new PhabricatorFile())->loadAllWhere(
      'phid IN (%Ls)',
      $file_phids), null, 'getPHID');

    foreach ($all_images as $image) {
      $image->attachFile($all_files[$image->getFilePHID()]);
    }

    $image_groups = mgroup($all_images, 'getMockID');

    foreach ($mocks as $mock) {
      $mock->attachImages($image_groups[$mock->getID()]);
    }
  }

  public function loadCoverFiles(array $mocks) {
    assert_instances_of($mocks, 'PholioMock');
    $cover_file_phids = mpull($mocks, 'getCoverPHID');
    $cover_files = mpull(id(new PhabricatorFile())->loadAllWhere(
      'phid IN (%Ls)',
      $cover_file_phids), null, 'getPHID');

    foreach ($mocks as $mock) {
      $mock->attachCoverFile($cover_files[$mock->getCoverPHID()]);
    }
  }
}
