<?php

final class ConpherenceParticipantQuery extends PhabricatorOffsetPagedQuery {

  private $participantPHIDs;

  public function withParticipantPHIDs(array $phids) {
    $this->participantPHIDs = $phids;
    return $this;
  }

  public function execute() {
    $table = new ConpherenceParticipant();
    $thread = new ConpherenceThread();

    $conn = $table->establishConnection('r');

    $data = queryfx_all(
      $conn,
      'SELECT * FROM %T participant JOIN %T thread
        ON participant.conpherencePHID = thread.phid %Q %Q %Q',
      $table->getTableName(),
      $thread->getTableName(),
      $this->buildWhereClause($conn),
      $this->buildOrderClause($conn),
      $this->buildLimitClause($conn));

    $participants = $table->loadAllFromArray($data);

    // TODO: Fix this, it's bogus.
    if ('garbage') {
      if (count($this->participantPHIDs) !== 1) {
        throw new Exception(
          pht(
            'This query only works when querying for exactly one participant '.
            'PHID!'));
      }
      // This will throw results away if we aren't doing a query for exactly
      // one participant PHID.
      $participants = mpull($participants, null, 'getConpherencePHID');
    }

    return $participants;
  }

  protected function buildWhereClause(AphrontDatabaseConnection $conn) {
    $where = array();

    if ($this->participantPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'participantPHID IN (%Ls)',
        $this->participantPHIDs);
    }

    return $this->formatWhereClause($where);
  }

  private function buildOrderClause(AphrontDatabaseConnection $conn) {
    return qsprintf(
      $conn,
      'ORDER BY thread.dateModified DESC, thread.id DESC, participant.id DESC');
  }

}
