<?php

final class PhabricatorMetaMTAMailQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $actorPHIDs;
  private $recipientPHIDs;
  private $createdMin;
  private $createdMax;

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

  public function withDateCreatedBetween($min, $max) {
    $this->createdMin = $min;
    $this->createdMax = $max;
    return $this;
  }

  protected function loadPage() {
    return $this->loadStandardPage($this->newResultObject());
  }

  protected function buildWhereClauseParts(AphrontDatabaseConnection $conn) {
    $where = parent::buildWhereClauseParts($conn);

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn,
        'mail.id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids !== null) {
      $where[] = qsprintf(
        $conn,
        'mail.phid IN (%Ls)',
        $this->phids);
    }

    if ($this->actorPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'mail.actorPHID IN (%Ls)',
        $this->actorPHIDs);
    }

    if ($this->recipientPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'recipient.dst IN (%Ls)',
        $this->recipientPHIDs);
    }

    if ($this->actorPHIDs === null && $this->recipientPHIDs === null) {
      $viewer = $this->getViewer();
      if (!$viewer->isOmnipotent()) {
        $where[] = qsprintf(
          $conn,
          'edge.dst = %s OR actorPHID = %s',
          $viewer->getPHID(),
          $viewer->getPHID());
      }
    }

    if ($this->createdMin !== null) {
      $where[] = qsprintf(
        $conn,
        'mail.dateCreated >= %d',
        $this->createdMin);
    }

    if ($this->createdMax !== null) {
      $where[] = qsprintf(
        $conn,
        'mail.dateCreated <= %d',
        $this->createdMax);
    }

    return $where;
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
