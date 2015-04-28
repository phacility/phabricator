<?php

final class PholioMockQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $authorPHIDs;
  private $statuses;

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

  public function withStatuses(array $statuses) {
    $this->statuses = $statuses;
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
      '%Q FROM %T mock %Q %Q %Q %Q %Q %Q',
      $this->buildSelectClause($conn_r),
      $table->getTableName(),
      $this->buildJoinClause($conn_r),
      $this->buildWhereClause($conn_r),
      $this->buildGroupClause($conn_r),
      $this->buildOrderClause($conn_r),
      $this->buildHavingClause($conn_r),
      $this->buildLimitClause($conn_r));

    $mocks = $table->loadAllFromArray($data);

    if ($mocks && $this->needImages) {
      self::loadImages($this->getViewer(), $mocks, $this->needInlineComments);
    }

    if ($mocks && $this->needCoverFiles) {
      $this->loadCoverFiles($mocks);
    }

    if ($mocks && $this->needTokenCounts) {
      $this->loadTokenCounts($mocks);
    }

    return $mocks;
  }

  protected function buildWhereClause(AphrontDatabaseConnection $conn_r) {
    $where = array();

    $where[] = $this->buildWhereClauseParts($conn_r);

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn_r,
        'mock.id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids !== null) {
      $where[] = qsprintf(
        $conn_r,
        'mock.phid IN (%Ls)',
        $this->phids);
    }

    if ($this->authorPHIDs !== null) {
      $where[] = qsprintf(
        $conn_r,
        'mock.authorPHID in (%Ls)',
        $this->authorPHIDs);
    }

    if ($this->statuses !== null) {
      $where[] = qsprintf(
        $conn_r,
        'mock.status IN (%Ls)',
        $this->statuses);
    }

    return $this->formatWhereClause($where);
  }

  public static function loadImages(
    PhabricatorUser $viewer,
    array $mocks,
    $need_inline_comments) {
    assert_instances_of($mocks, 'PholioMock');

    $mock_map = mpull($mocks, null, 'getID');
    $all_images = id(new PholioImageQuery())
      ->setViewer($viewer)
      ->setMockCache($mock_map)
      ->withMockIDs(array_keys($mock_map))
      ->needInlineComments($need_inline_comments)
      ->execute();

    $image_groups = mgroup($all_images, 'getMockID');

    foreach ($mocks as $mock) {
      $mock_images = idx($image_groups, $mock->getID(), array());
      $mock->attachAllImages($mock_images);
      $active_images = mfilter($mock_images, 'getIsObsolete', true);
      $mock->attachImages(msort($active_images, 'getSequence'));
    }
  }

  private function loadCoverFiles(array $mocks) {
    assert_instances_of($mocks, 'PholioMock');
    $cover_file_phids = mpull($mocks, 'getCoverPHID');
    $cover_files = id(new PhabricatorFileQuery())
      ->setViewer($this->getViewer())
      ->withPHIDs($cover_file_phids)
      ->execute();

    $cover_files = mpull($cover_files, null, 'getPHID');

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

  public function getQueryApplicationClass() {
    return 'PhabricatorPholioApplication';
  }

  protected function getPrimaryTableAlias() {
    return 'mock';
  }

}
