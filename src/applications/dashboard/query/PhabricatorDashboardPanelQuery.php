<?php

final class PhabricatorDashboardPanelQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $archived;
  private $panelTypes;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withArchived($archived) {
    $this->archived = $archived;
    return $this;
  }

  public function withPanelTypes(array $types) {
    $this->panelTypes = $types;
    return $this;
  }

  protected function loadPage() {
    return $this->loadStandardPage($this->newResultObject());
  }

  public function newResultObject() {
    // TODO: If we don't do this, SearchEngine explodes when trying to
    // enumerate custom fields. For now, just give the panel a default panel
    // type so custom fields work. In the long run, we may want to find a
    // cleaner or more general approach for this.
    $text_type = id(new PhabricatorDashboardTextPanelType())
      ->getPanelTypeKey();

    return id(new PhabricatorDashboardPanel())
      ->setPanelType($text_type);
  }

  protected function buildWhereClauseParts(AphrontDatabaseConnection $conn) {
    $where = parent::buildWhereClauseParts($conn);

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

    if ($this->archived !== null) {
      $where[] = qsprintf(
        $conn,
        'isArchived = %d',
        (int)$this->archived);
    }

    if ($this->panelTypes !== null) {
      $where[] = qsprintf(
        $conn,
        'panelType IN (%Ls)',
        $this->panelTypes);
    }

    return $where;
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorDashboardApplication';
  }

}
