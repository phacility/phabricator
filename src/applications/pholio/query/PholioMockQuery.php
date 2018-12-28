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

  public function newResultObject() {
    return new PholioMock();
  }

  protected function loadPage() {
    if ($this->needInlineComments && !$this->needImages) {
      throw new Exception(
        pht(
          'You can not query for inline comments without also querying for '.
          'images.'));
    }

    return $this->loadStandardPage(new PholioMock());
  }

  protected function buildWhereClauseParts(AphrontDatabaseConnection $conn) {
    $where = parent::buildWhereClauseParts($conn);

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn,
        'mock.id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids !== null) {
      $where[] = qsprintf(
        $conn,
        'mock.phid IN (%Ls)',
        $this->phids);
    }

    if ($this->authorPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'mock.authorPHID in (%Ls)',
        $this->authorPHIDs);
    }

    if ($this->statuses !== null) {
      $where[] = qsprintf(
        $conn,
        'mock.status IN (%Ls)',
        $this->statuses);
    }

    return $where;
  }

  protected function didFilterPage(array $mocks) {
    $viewer = $this->getViewer();

    if ($this->needImages) {
      $images = id(new PholioImageQuery())
        ->setViewer($viewer)
        ->withMocks($mocks)
        ->needInlineComments($this->needInlineComments)
        ->execute();

      $image_groups = mgroup($images, 'getMockPHID');
      foreach ($mocks as $mock) {
        $images = idx($image_groups, $mock->getPHID(), array());
        $mock->attachImages($images);
      }
    }

    if ($this->needCoverFiles) {
      $cover_files = id(new PhabricatorFileQuery())
        ->setViewer($viewer)
        ->withPHIDs(mpull($mocks, 'getCoverPHID'))
        ->execute();
      $cover_files = mpull($cover_files, null, 'getPHID');

      foreach ($mocks as $mock) {
        $file = idx($cover_files, $mock->getCoverPHID());
        if (!$file) {
          $file = PhabricatorFile::loadBuiltin(
            $viewer,
            'missing.png');
        }
        $mock->attachCoverFile($file);
      }
    }

    if ($this->needTokenCounts) {
      $counts = id(new PhabricatorTokenCountQuery())
        ->withObjectPHIDs(mpull($mocks, 'getPHID'))
        ->execute();

      foreach ($mocks as $mock) {
        $token_count = idx($counts, $mock->getPHID(), 0);
        $mock->attachTokenCount($token_count);
      }
    }

    return $mocks;
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorPholioApplication';
  }

  protected function getPrimaryTableAlias() {
    return 'mock';
  }

}
