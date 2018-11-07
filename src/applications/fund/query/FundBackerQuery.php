<?php

final class FundBackerQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $statuses;

  private $initiativePHIDs;
  private $backerPHIDs;

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

  public function withInitiativePHIDs(array $phids) {
    $this->initiativePHIDs = $phids;
    return $this;
  }

  public function withBackerPHIDs(array $phids) {
    $this->backerPHIDs = $phids;
    return $this;
  }

  protected function loadPage() {
    $table = new FundBacker();
    $conn_r = $table->establishConnection('r');

    $rows = queryfx_all(
      $conn_r,
      'SELECT * FROM %T %Q %Q %Q',
      $table->getTableName(),
      $this->buildWhereClause($conn_r),
      $this->buildOrderClause($conn_r),
      $this->buildLimitClause($conn_r));

    return $table->loadAllFromArray($rows);
  }

  protected function willFilterPage(array $backers) {
    $initiative_phids = mpull($backers, 'getInitiativePHID');
    $initiatives = id(new PhabricatorObjectQuery())
      ->setParentQuery($this)
      ->setViewer($this->getViewer())
      ->withPHIDs($initiative_phids)
      ->execute();
    $initiatives = mpull($initiatives, null, 'getPHID');

    foreach ($backers as $backer) {
      $initiative_phid = $backer->getInitiativePHID();
      $initiative = idx($initiatives, $initiative_phid);
      $backer->attachInitiative($initiative);
    }

    return $backers;
  }

  protected function buildWhereClause(AphrontDatabaseConnection $conn) {
    $where = array();

    $where[] = $this->buildPagingClause($conn);

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

    if ($this->initiativePHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'initiativePHID IN (%Ls)',
        $this->initiativePHIDs);
    }

    if ($this->backerPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'backerPHID IN (%Ls)',
        $this->backerPHIDs);
    }

    if ($this->statuses !== null) {
      $where[] = qsprintf(
        $conn,
        'status IN (%Ls)',
        $this->statuses);
    }

    return $this->formatWhereClause($conn, $where);
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorFundApplication';
  }

}
