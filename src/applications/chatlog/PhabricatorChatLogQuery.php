<?php

final class PhabricatorChatLogQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $channels;
  private $maximumEpoch;

  public function withChannels(array $channels) {
    $this->channels = $channels;
    return $this;
  }

  public function withMaximumEpoch($epoch) {
    $this->maximumEpoch = $epoch;
    return $this;
  }

  public function loadPage() {
    $table  = new PhabricatorChatLogEvent();
    $conn_r = $table->establishConnection('r');

    $data = queryfx_all(
      $conn_r,
      'SELECT * FROM %T e %Q %Q %Q',
      $table->getTableName(),
      $this->buildWhereClause($conn_r),
      $this->buildOrderClause($conn_r),
      $this->buildLimitClause($conn_r));

    $logs = $table->loadAllFromArray($data);

    return $logs;
  }

  private function buildWhereClause($conn_r) {
    $where = array();

    $where[] = $this->buildPagingClause($conn_r);

    if ($this->maximumEpoch) {
      $where[] = qsprintf(
        $conn_r,
        'epoch <= %d',
        $this->maximumEpoch);
    }

    if ($this->channels) {
      $where[] = qsprintf(
        $conn_r,
        'channel IN (%Ls)',
        $this->channels);
    }

    return $this->formatWhereClause($where);
  }
}
