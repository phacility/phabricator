<?php

final class PhabricatorCalendarEventQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $rangeBegin;
  private $rangeEnd;
  private $invitedPHIDs;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withDateRange($begin, $end) {
    $this->rangeBegin = $begin;
    $this->rangeEnd = $end;
    return $this;
  }

  public function withInvitedPHIDs(array $phids) {
    $this->invitedPHIDs = $phids;
    return $this;
  }

  protected function loadPage() {
    $table = new PhabricatorCalendarEvent();
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

  protected function buildWhereClause($conn_r) {
    $where = array();

    if ($this->ids) {
      $where[] = qsprintf(
        $conn_r,
        'id IN (%Ld)',
        $this->ids);
    }

    if ($this->rangeBegin || $this->rangeEnd) {
      $where[] = qsprintf(
        $conn_r,
        'dateTo >= %d AND dateFrom <= %d',
        $this->rangeBegin,
        $this->rangeEnd);
    }

    if ($this->invitedPHIDs) {
      $where[] = qsprintf(
        $conn_r,
        'userPHID IN (%Ls)',
        $this->invitedPHIDs);
    }

    $where[] = $this->buildPagingClause($conn_r);

    return $this->formatWhereClause($where);
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorApplicationCalendar';
  }

}
