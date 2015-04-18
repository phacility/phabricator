<?php

final class FundInitiativeQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $ownerPHIDs;
  private $statuses;

  private $needProjectPHIDs;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withOwnerPHIDs(array $phids) {
    $this->ownerPHIDs = $phids;
    return $this;
  }

  public function withStatuses(array $statuses) {
    $this->statuses = $statuses;
    return $this;
  }

  public function needProjectPHIDs($need) {
    $this->needProjectPHIDs = $need;
    return $this;
  }

  protected function loadPage() {
    $table = new FundInitiative();
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

  protected function didFilterPage(array $initiatives) {

    if ($this->needProjectPHIDs) {
      $edge_query = id(new PhabricatorEdgeQuery())
        ->withSourcePHIDs(mpull($initiatives, 'getPHID'))
        ->withEdgeTypes(
          array(
            PhabricatorProjectObjectHasProjectEdgeType::EDGECONST,
          ));
      $edge_query->execute();

      foreach ($initiatives as $initiative) {
        $phids = $edge_query->getDestinationPHIDs(
          array(
            $initiative->getPHID(),
          ));
        $initiative->attachProjectPHIDs($phids);
      }
    }

    return $initiatives;
  }

  protected function buildWhereClause(AphrontDatabaseConnection $conn_r) {
    $where = array();

    $where[] = $this->buildPagingClause($conn_r);

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn_r,
        'id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids !== null) {
      $where[] = qsprintf(
        $conn_r,
        'phid IN (%Ls)',
        $this->phids);
    }

    if ($this->ownerPHIDs !== null) {
      $where[] = qsprintf(
        $conn_r,
        'ownerPHID IN (%Ls)',
        $this->ownerPHIDs);
    }

    if ($this->statuses !== null) {
      $where[] = qsprintf(
        $conn_r,
        'status IN (%Ls)',
        $this->statuses);
    }

    return $this->formatWhereClause($where);
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorFundApplication';
  }

}
