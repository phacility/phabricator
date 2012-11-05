<?php

final class PhabricatorPasteQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $authorPHIDs;
  private $parentPHIDs;

  private $needContent;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withAuthorPHIDs(array $phids) {
    $this->authorPHIDs = $phids;
    return $this;
  }

  public function withParentPHIDs(array $phids) {
    $this->parentPHIDs = $phids;
    return $this;
  }

  public function needContent($need_content) {
    $this->needContent = $need_content;
    return $this;
  }

  public function loadPage() {
    $table = new PhabricatorPaste();
    $conn_r = $table->establishConnection('r');

    $data = queryfx_all(
      $conn_r,
      'SELECT paste.* FROM %T paste %Q %Q %Q',
      $table->getTableName(),
      $this->buildWhereClause($conn_r),
      $this->buildOrderClause($conn_r),
      $this->buildLimitClause($conn_r));

    $pastes = $table->loadAllFromArray($data);

    if ($pastes && $this->needContent) {
      $file_phids = mpull($pastes, 'getFilePHID');
      $files = id(new PhabricatorFile())->loadAllWhere(
        'phid IN (%Ls)',
        $file_phids);
      $files = mpull($files, null, 'getPHID');
      foreach ($pastes as $paste) {
        $file = idx($files, $paste->getFilePHID());
        if ($file) {
          $paste->attachContent($file->loadFileData());
        } else {
          $paste->attachContent('');
        }
      }
    }

    return $pastes;
  }

  protected function buildWhereClause($conn_r) {
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
        'authorPHID IN (%Ls)',
        $this->authorPHIDs);
    }

    if ($this->parentPHIDs) {
      $where[] = qsprintf(
        $conn_r,
        'parentPHID IN (%Ls)',
        $this->parentPHIDs);
    }

    return $this->formatWhereClause($where);
  }

}
