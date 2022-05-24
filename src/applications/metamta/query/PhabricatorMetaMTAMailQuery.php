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

  protected function buildJoinClauseParts(AphrontDatabaseConnection $conn) {
    $joins = parent::buildJoinClauseParts($conn);

    if ($this->shouldJoinRecipients()) {
      $joins[] = qsprintf(
        $conn,
        'JOIN %T recipient
          ON mail.phid = recipient.src
            AND recipient.type = %d
            AND recipient.dst IN (%Ls)',
        PhabricatorEdgeConfig::TABLE_NAME_EDGE,
        PhabricatorMetaMTAMailHasRecipientEdgeType::EDGECONST,
        $this->recipientPHIDs);
    }

    return $joins;
  }

  private function shouldJoinRecipients() {
    if ($this->recipientPHIDs === null) {
      return false;
    }

    return true;
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

  protected function shouldGroupQueryResultRows() {
    if ($this->shouldJoinRecipients()) {
      if (count($this->recipientPHIDs) > 1) {
        return true;
      }
    }

    return parent::shouldGroupQueryResultRows();
  }

}
