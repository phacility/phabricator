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

final class PhabricatorChatLogQuery {

  private $channels;

  private $limit;

  public function withChannels(array $channels) {
    $this->channels = $channels;
    return $this;
  }

  public function setLimit($limit) {
    $this->limit = $limit;
    return $this;
  }

  public function execute() {
    $table = new PhabricatorChatLogEvent();
    $conn_r = $table->establishConnection('r');

    $where_clause = $this->buildWhereClause($conn_r);

    $limit_clause = '';
    if ($this->limit) {
      $limit_clause = qsprintf(
        $conn_r,
        'LIMIT %d',
        $this->limit);
    }

    $data = queryfx_all(
      $conn_r,
      'SELECT * FROM %T e %Q ORDER BY epoch ASC %Q',
      $table->getTableName(),
      $where_clause,
      $limit_clause);

    $logs = $table->loadAllFromArray($data);

    return $logs;
  }

  private function buildWhereClause($conn_r) {
    $where = array();

    if ($this->channels) {
      $where[] = qsprintf(
        $conn_r,
        'channel IN (%Ls)',
        $this->channels);
    }

    if ($where) {
      $where = 'WHERE ('.implode(') AND (', $where).')';
    } else {
      $where = '';
    }

    return $where;
  }

}
