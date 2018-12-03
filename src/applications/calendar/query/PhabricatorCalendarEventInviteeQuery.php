<?php

final class PhabricatorCalendarEventInviteeQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $eventPHIDs;
  private $inviteePHIDs;
  private $inviterPHIDs;
  private $statuses;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withEventPHIDs(array $phids) {
    $this->eventPHIDs = $phids;
    return $this;
  }

  public function withInviteePHIDs(array $phids) {
    $this->inviteePHIDs = $phids;
    return $this;
  }

  public function withInviterPHIDs(array $phids) {
    $this->inviterPHIDs = $phids;
    return $this;
  }

  public function withStatuses(array $statuses) {
    $this->statuses = $statuses;
    return $this;
  }

  protected function loadPage() {
    $table = new PhabricatorCalendarEventInvitee();
    $conn_r = $table->establishConnection('r');

    $data = queryfx_all(
      $conn_r,
      'SELECT * FROM %T %Q %Q %Q',
      $table->getTableName(),
      $this->buildWhereClause($conn_r),
      $this->buildOrderClause($conn_r),
      $this->buildLimitClause($conn_r));

    return $table->loadAllFromArray($data);
  }

  protected function buildWhereClause(AphrontDatabaseConnection $conn) {
    $where = array();

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn,
        'id IN (%Ld)',
        $this->ids);
    }

    if ($this->eventPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'eventPHID IN (%Ls)',
        $this->eventPHIDs);
    }

    if ($this->inviteePHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'inviteePHID IN (%Ls)',
        $this->inviteePHIDs);
    }

    if ($this->inviterPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'inviterPHID IN (%Ls)',
        $this->inviterPHIDs);
    }

    if ($this->statuses !== null) {
      $where[] = qsprintf(
        $conn,
        'status = %d',
        $this->statuses);
    }

    $where[] = $this->buildPagingClause($conn);

    return $this->formatWhereClause($conn, $where);
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorCalendarApplication';
  }

}
