<?php

final class PhabricatorCalendarImportQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $authorPHIDs;
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

  public function newResultObject() {
    return new PhabricatorCalendarImport();
  }

  protected function buildWhereClauseParts(AphrontDatabaseConnection $conn) {
    $where = parent::buildWhereClauseParts($conn);

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn,
        'import.id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids !== null) {
      $where[] = qsprintf(
        $conn,
        'import.phid IN (%Ls)',
        $this->phids);
    }

    if ($this->authorPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'import.authorPHID IN (%Ls)',
        $this->authorPHIDs);
    }

    if ($this->isDisabled !== null) {
      $where[] = qsprintf(
        $conn,
        'import.isDisabled = %d',
        (int)$this->isDisabled);
    }

    return $where;
  }

  protected function willFilterPage(array $page) {
    $engines = PhabricatorCalendarImportEngine::getAllImportEngines();
    foreach ($page as $key => $import) {
      $engine_type = $import->getEngineType();
      $engine = idx($engines, $engine_type);

      if (!$engine) {
        unset($page[$key]);
        $this->didRejectResult($import);
        continue;
      }

      $import->attachEngine(clone $engine);
    }

    return $page;
  }

  protected function getPrimaryTableAlias() {
    return 'import';
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorCalendarApplication';
  }

}
