<?php

/**
 * @group conpherence
 */
final class ConpherenceParticipantQuery
  extends PhabricatorOffsetPagedQuery {

  private $conpherencePHIDs;
  private $participantPHIDs;
  private $dateTouched;
  private $dateTouchedSort;
  private $participationStatus;

  public function withConpherencePHIDs(array $phids) {
    $this->conpherencePHIDs = $phids;
    return $this;
  }

  public function withParticipantPHIDs(array $phids) {
    $this->participantPHIDs = $phids;
    return $this;
  }

  public function withDateTouched($date, $sort = null) {
    $this->dateTouched = $date;
    $this->dateTouchedSort = $sort ? $sort : '<';
    return $this;
  }

  public function withParticipationStatus($participation_status) {
    $this->participationStatus = $participation_status;
    return $this;
  }

  public function execute() {
    $table = new ConpherenceParticipant();
    $conn_r = $table->establishConnection('r');

    $data = queryfx_all(
      $conn_r,
      'SELECT * FROM %T participant %Q %Q %Q',
      $table->getTableName(),
      $this->buildWhereClause($conn_r),
      $this->buildOrderClause($conn_r),
      $this->buildLimitClause($conn_r));

    $participants = $table->loadAllFromArray($data);

    $participants = mpull($participants, null, 'getConpherencePHID');

    return $participants;
  }

  private function buildWhereClause($conn_r) {
    $where = array();

    if ($this->conpherencePHIDs) {
      $where[] = qsprintf(
        $conn_r,
        'conpherencePHID IN (%Ls)',
        $this->conpherencePHIDs);
    }

    if ($this->participantPHIDs) {
      $where[] = qsprintf(
        $conn_r,
        'participantPHID IN (%Ls)',
        $this->participantPHIDs);
    }

    if ($this->participationStatus !== null) {
      $where[] = qsprintf(
        $conn_r,
        'participationStatus = %d',
        $this->participationStatus);
    }

    if ($this->dateTouched) {
      if ($this->dateTouchedSort) {
        $where[] = qsprintf(
          $conn_r,
          'dateTouched %Q %d',
          $this->dateTouchedSort,
          $this->dateTouched);
      }
    }

    return $this->formatWhereClause($where);
  }

  private function buildOrderClause(AphrontDatabaseConnection $conn_r) {
    return 'ORDER BY dateTouched DESC';
  }

}
