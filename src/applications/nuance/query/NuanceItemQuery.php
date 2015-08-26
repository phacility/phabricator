<?php

final class NuanceItemQuery
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
    return new NuanceItem();
  }

  protected function loadPage() {
    return $this->loadStandardPage($this->newResultObject());
  }

  protected function willFilterPage(array $items) {
    $source_phids = mpull($items, 'getSourcePHID');

    // NOTE: We always load sources, even if the viewer can't formally see
    // them. If they can see the item, they're allowed to be aware of the
    // source in some sense.
    $sources = id(new NuanceSourceQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withPHIDs($source_phids)
      ->execute();
    $sources = mpull($sources, null, 'getPHID');

    foreach ($items as $key => $item) {
      $source = idx($sources, $item->getSourcePHID());
      if (!$source) {
        $this->didRejectResult($items[$key]);
        unset($items[$key]);
        continue;
      }
      $item->attachSource($source);
    }

    return $items;
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
