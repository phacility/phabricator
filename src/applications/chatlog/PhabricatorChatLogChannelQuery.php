<?php

final class PhabricatorChatLogChannelQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $channels;

  public function withChannelNames(array $channels) {
    $this->channels = $channels;
    return $this;
  }

  public function loadPage() {
    $table  = new PhabricatorChatLogChannel();
    $conn_r = $table->establishConnection('r');

    $data = queryfx_all(
      $conn_r,
      'SELECT * FROM %T c %Q %Q %Q',
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

    if ($this->channels) {
      $where[] = qsprintf(
        $conn_r,
        'channelName IN (%Ls)',
        $this->channels);
    }

    return $this->formatWhereClause($where);
  }
}
