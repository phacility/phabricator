<?php

final class NuanceItemCommandQuery
  extends NuanceQuery {

  private $ids;
  private $itemPHIDs;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withItemPHIDs(array $item_phids) {
    $this->itemPHIDs = $item_phids;
    return $this;
  }

  public function newResultObject() {
    return new NuanceItemCommand();
  }

  protected function loadPage() {
    return $this->loadStandardPage($this->newResultObject());
  }

  protected function buildWhereClauseParts(AphrontDatabaseConnection $conn) {
    $where = parent::buildWhereClauseParts($conn);

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn,
        'id IN (%Ld)',
        $this->ids);
    }

    if ($this->itemPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'itemPHID IN (%Ls)',
        $this->itemPHIDs);
    }

    return $where;
  }

}
