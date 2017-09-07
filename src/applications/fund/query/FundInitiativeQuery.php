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

  public function newResultObject() {
    return new FundInitiative();
  }

  protected function loadPage() {
    return $this->loadStandardPage($this->newResultObject());
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

    if ($this->ownerPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'ownerPHID IN (%Ls)',
        $this->ownerPHIDs);
    }

    if ($this->statuses !== null) {
      $where[] = qsprintf(
        $conn,
        'status IN (%Ls)',
        $this->statuses);
    }

    return $where;
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorFundApplication';
  }

}
