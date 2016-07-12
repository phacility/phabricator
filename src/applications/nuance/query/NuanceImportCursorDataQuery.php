<?php

final class NuanceImportCursorDataQuery
  extends NuanceQuery {

  private $ids;
  private $phids;
  private $sourcePHIDs;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withSourcePHIDs(array $source_phids) {
    $this->sourcePHIDs = $source_phids;
    return $this;
  }

  public function newResultObject() {
    return new NuanceImportCursorData();
  }

  protected function loadPage() {
    return $this->loadStandardPage($this->newResultObject());
  }

  protected function buildWhereClauseParts(AphrontDatabaseConnection $conn) {
    $where = parent::buildWhereClauseParts($conn);

    if ($this->sourcePHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'sourcePHID IN (%Ls)',
        $this->sourcePHIDs);
    }

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

    return $where;
  }

}
