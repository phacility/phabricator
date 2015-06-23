<?php

final class PhabricatorMetaMTAMailQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $actorPHIDs;
  private $recipientPHIDs;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withActorPHIDs(array $phids) {
    $this->actorPHIDs = $phids;
    return $this;
  }

  public function withRecipientPHIDs(array $phids) {
    $this->recipientPHIDs = $phids;
    return $this;
  }

  protected function loadPage() {
    return $this->loadStandardPage($this->newResultObject());
  }

  protected function buildWhereClause(AphrontDatabaseConnection $conn_r) {
    $where = array();

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn_r,
        'mail.id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids !== null) {
      $where[] = qsprintf(
        $conn_r,
        'mail.phid IN (%Ls)',
        $this->phids);
    }

    if ($this->actorPHIDs !== null) {
      $where[] = qsprintf(
        $conn_r,
        'mail.actorPHID IN (%Ls)',
        $this->actorPHIDs);
    }

    if ($this->recipientPHIDs !== null) {
      $where[] = qsprintf(
        $conn_r,
        'recipient.dst IN (%Ls)',
        $this->recipientPHIDs);
    }

    if ($this->actorPHIDs === null && $this->recipientPHIDs === null) {
      $viewer = $this->getViewer();
      $where[] = qsprintf(
        $conn_r,
        'edge.dst = %s OR actorPHID = %s',
        $viewer->getPHID(),
        $viewer->getPHID());
    }

    $where[] = $this->buildPagingClause($conn_r);

    return $this->formatWhereClause($where);
  }

  protected function buildJoinClause(AphrontDatabaseConnection $conn) {
    $joins = array();

    if ($this->actorPHIDs === null && $this->recipientPHIDs === null) {
      $joins[] = qsprintf(
        $conn,
        'LEFT JOIN %T edge ON mail.phid = edge.src AND edge.type = %d',
        PhabricatorEdgeConfig::TABLE_NAME_EDGE,
        PhabricatorMetaMTAMailHasRecipientEdgeType::EDGECONST);
    }

    if ($this->recipientPHIDs !== null) {
      $joins[] = qsprintf(
        $conn,
        'LEFT JOIN %T recipient '.
        'ON mail.phid = recipient.src AND recipient.type = %d',
        PhabricatorEdgeConfig::TABLE_NAME_EDGE,
        PhabricatorMetaMTAMailHasRecipientEdgeType::EDGECONST);
    }

    return implode(' ', $joins);
  }

  protected function getPrimaryTableAlias() {
    return 'mail';
  }

  public function newResultObject() {
    return new PhabricatorMetaMTAMail();
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorMetaMTAApplication';
  }

}
