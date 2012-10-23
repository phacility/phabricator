<?php

/*
 * Copyright 2012 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

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
