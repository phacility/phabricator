<?php

/**
 * @group phriction
 */
final class PhabricatorMacroQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $authors;
  private $names;
  private $nameLike;

  private $status = 'status-any';
  const STATUS_ANY = 'status-any';
  const STATUS_ACTIVE = 'status-active';

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withAuthorPHIDs(array $authors) {
    $this->authors = $authors;
    return $this;
  }

  public function withNameLike($name) {
    $this->nameLike = $name;
    return $this;
  }

  public function withNames(array $names) {
    $this->names = $names;
    return $this;
  }

  public function withStatus($status) {
    $this->status = $status;
    return $this;
  }

  protected function loadPage() {
    $macro_table = new PhabricatorFileImageMacro();
    $conn = $macro_table->establishConnection('r');

    $rows = queryfx_all(
      $conn,
      'SELECT * FROM %T m %Q %Q %Q %Q',
      $macro_table->getTableName(),
      $this->buildJoinClause($conn),
      $this->buildWhereClause($conn),
      $this->buildOrderClause($conn),
      $this->buildLimitClause($conn));

    return $macro_table->loadAllFromArray($rows);
  }

  protected function buildJoinClause(AphrontDatabaseConnection $conn) {
    $joins = array();

    if ($this->authors) {
      $file_table = new PhabricatorFile();
      $joins[] = qsprintf(
        $conn,
        'JOIN %T f ON m.filePHID = f.phid',
        $file_table->getTableName());
    }

    return implode(' ', $joins);
  }

  protected function buildWhereClause(AphrontDatabaseConnection $conn) {
    $where = array();

    if ($this->ids) {
      $where[] = qsprintf(
        $conn,
        'm.id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids) {
      $where[] = qsprintf(
        $conn,
        'm.phid IN (%Ls)',
        $this->phids);
    }

    if ($this->authors) {
      $where[] = qsprintf(
        $conn,
        'f.authorPHID IN (%Ls)',
        $this->authors);
    }

    if ($this->nameLike) {
      $where[] = qsprintf(
        $conn,
        'm.name LIKE %~',
        $this->nameLike);
    }

    if ($this->names) {
      $where[] = qsprintf(
        $conn,
        'm.name IN (%Ls)',
        $this->names);
    }

    if ($this->status == self::STATUS_ACTIVE) {
      $where[] = qsprintf(
        $conn,
        'm.isDisabled = 0');
    }

    $where[] = $this->buildPagingClause($conn);

    return $this->formatWhereClause($where);
  }

  protected function willFilterPage(array $macros) {
    if (!$macros) {
      return array();
    }

    $file_phids = mpull($macros, 'getFilePHID');
    $files = id(new PhabricatorFileQuery())
      ->setViewer($this->getViewer())
      ->withPHIDs($file_phids)
      ->execute();
    $files = mpull($files, null, 'getPHID');

    foreach ($macros as $key => $macro) {
      $file = idx($files, $macro->getFilePHID());
      if (!$file) {
        unset($macros[$key]);
        continue;
      }
      $macro->attachFile($file);
    }

    return $macros;
  }

  protected function getPagingColumn() {
    return 'm.id';
  }

}
