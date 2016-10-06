<?php

final class PhabricatorCalendarExportQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $authorPHIDs;
  private $secretKeys;
  private $isDisabled;

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

  public function withIsDisabled($is_disabled) {
    $this->isDisabled = $is_disabled;
    return $this;
  }

  public function withSecretKeys(array $keys) {
    $this->secretKeys = $keys;
    return $this;
  }

  public function newResultObject() {
    return new PhabricatorCalendarExport();
  }

  protected function loadPage() {
    return $this->loadStandardPage($this->newResultObject());
  }

  protected function buildWhereClauseParts(AphrontDatabaseConnection $conn) {
    $where = parent::buildWhereClauseParts($conn);

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn,
        'export.id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids !== null) {
      $where[] = qsprintf(
        $conn,
        'export.phid IN (%Ls)',
        $this->phids);
    }

    if ($this->authorPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'export.authorPHID IN (%Ls)',
        $this->authorPHIDs);
    }

    if ($this->isDisabled !== null) {
      $where[] = qsprintf(
        $conn,
        'export.isDisabled = %d',
        (int)$this->isDisabled);
    }

    if ($this->secretKeys !== null) {
      $where[] = qsprintf(
        $conn,
        'export.secretKey IN (%Ls)',
        $this->secretKeys);
    }

    return $where;
  }

  protected function getPrimaryTableAlias() {
    return 'export';
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorCalendarApplication';
  }

}
