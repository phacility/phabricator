<?php

/**
 * Query class that answers the question:
 *
 * - Q: How many unread conpherences am I participating in?
 * - A:
 *     id(new ConpherenceParticipantCountQuery())
 *     ->withParticipantPHIDs(array($my_phid))
 *     ->withParticipationStatus(ConpherenceParticipationStatus::BEHIND)
 *     ->execute();
 */
final class ConpherenceParticipantCountQuery
  extends PhabricatorOffsetPagedQuery {

  private $participantPHIDs;
  private $participationStatus;

  public function withParticipantPHIDs(array $phids) {
    $this->participantPHIDs = $phids;
    return $this;
  }

  public function withParticipationStatus($participation_status) {
    $this->participationStatus = $participation_status;
    return $this;
  }

  public function execute() {
    $table = new ConpherenceParticipant();
    $conn_r = $table->establishConnection('r');

    $rows = queryfx_all(
      $conn_r,
      'SELECT COUNT(*) as count, participantPHID '.
      'FROM %T participant %Q %Q %Q',
      $table->getTableName(),
      $this->buildWhereClause($conn_r),
      $this->buildGroupByClause($conn_r),
      $this->buildLimitClause($conn_r));

    return ipull($rows, 'count', 'participantPHID');
  }

  protected function buildWhereClause(AphrontDatabaseConnection $conn_r) {
    $where = array();

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

    return $this->formatWhereClause($where);
  }

  private function buildGroupByClause(AphrontDatabaseConnection $conn_r) {
    $group_by = qsprintf(
      $conn_r,
      'GROUP BY participantPHID');

    return $group_by;
  }

}
