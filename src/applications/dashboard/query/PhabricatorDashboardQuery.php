<?php

final class PhabricatorDashboardQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $statuses;
  private $authorPHIDs;
  private $canEdit;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withStatuses(array $statuses) {
    $this->statuses = $statuses;
    return $this;
  }

  public function withAuthorPHIDs(array $authors) {
    $this->authorPHIDs = $authors;
    return $this;
  }

  public function withCanEdit($can_edit) {
    $this->canEdit = $can_edit;
    return $this;
  }

  public function newResultObject() {
    return new PhabricatorDashboard();
  }

  protected function didFilterPage(array $dashboards) {

    $phids = mpull($dashboards, 'getPHID');

    if ($this->canEdit) {
      $dashboards = id(new PhabricatorPolicyFilter())
        ->setViewer($this->getViewer())
        ->requireCapabilities(array(
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
        ->apply($dashboards);
    }

    return $dashboards;
  }

  protected function buildWhereClauseParts(AphrontDatabaseConnection $conn) {
    $where = parent::buildWhereClauseParts($conn);

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn,
        'dashboard.id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids !== null) {
      $where[] = qsprintf(
        $conn,
        'dashboard.phid IN (%Ls)',
        $this->phids);
    }

    if ($this->statuses !== null) {
      $where[] = qsprintf(
        $conn,
        'dashboard.status IN (%Ls)',
        $this->statuses);
    }

    if ($this->authorPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'dashboard.authorPHID IN (%Ls)',
        $this->authorPHIDs);
    }

    return $where;
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorDashboardApplication';
  }

  protected function getPrimaryTableAlias() {
    return 'dashboard';
  }

}
