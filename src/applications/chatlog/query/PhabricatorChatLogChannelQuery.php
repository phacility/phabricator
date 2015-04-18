<?php

final class PhabricatorChatLogChannelQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $channels;
  private $channelIDs;

  public function withChannelNames(array $channels) {
    $this->channels = $channels;
    return $this;
  }

  public function withIDs(array $channel_ids) {
    $this->channelIDs = $channel_ids;
    return $this;
  }

  protected function loadPage() {
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

  protected function buildWhereClause(AphrontDatabaseConnection $conn_r) {
    $where = array();

    $where[] = $this->buildPagingClause($conn_r);

    if ($this->channelIDs) {
      $where[] = qsprintf(
        $conn_r,
        'id IN (%Ld)',
        $this->channelIDs);

    }

    if ($this->channels) {
      $where[] = qsprintf(
        $conn_r,
        'channelName IN (%Ls)',
        $this->channels);
    }

    return $this->formatWhereClause($where);
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorChatLogApplication';
  }

}
