<?php

final class PhabricatorChatLogQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $channelIDs;
  private $maximumEpoch;

  public function withChannelIDs(array $channel_ids) {
    $this->channelIDs = $channel_ids;
    return $this;
  }

  public function withMaximumEpoch($epoch) {
    $this->maximumEpoch = $epoch;
    return $this;
  }

  protected function loadPage() {
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

  protected function willFilterPage(array $events) {
    $channel_ids = mpull($events, 'getChannelID', 'getChannelID');

    $channels = id(new PhabricatorChatLogChannelQuery())
      ->setViewer($this->getViewer())
      ->withIDs($channel_ids)
      ->execute();
    $channels = mpull($channels, null, 'getID');

    foreach ($events as $key => $event) {
      $channel = idx($channels, $event->getChannelID());
      if (!$channel) {
        unset($events[$key]);
        continue;
      }

      $event->attachChannel($channel);
    }

    return $events;
  }

  protected function buildWhereClause(AphrontDatabaseConnection $conn) {
    $where = array();

    $where[] = $this->buildPagingClause($conn);

    if ($this->maximumEpoch !== null) {
      $where[] = qsprintf(
        $conn,
        'epoch <= %d',
        $this->maximumEpoch);
    }

    if ($this->channelIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'channelID IN (%Ld)',
        $this->channelIDs);
    }

    return $this->formatWhereClause($conn, $where);
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorChatLogApplication';
  }

}
