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

final class DrydockLogQuery extends PhabricatorOffsetPagedQuery {

  private $resourceIDs;
  private $leaseIDs;

  public function withResourceIDs(array $ids) {
    $this->resourceIDs = $ids;
    return $this;
  }

  public function withLeaseIDs(array $ids) {
    $this->leaseIDs = $ids;
    return $this;
  }

  public function execute() {
    $table = new DrydockLog();
    $conn_r = $table->establishConnection('r');

    $where = $this->buildWhereClause($conn_r);
    $order = $this->buildOrderClause($conn_r);
    $limit = $this->buildLimitClause($conn_r);

    $data = queryfx_all(
      $conn_r,
      'SELECT log.* FROM %T log %Q %Q %Q',
      $table->getTableName(),
      $where,
      $order,
      $limit);

    return $table->loadAllFromArray($data);
  }

  private function buildWhereClause($conn_r) {
    $where = array();

    if ($this->resourceIDs) {
      $where[] = qsprintf(
        $conn_r,
        'resourceID IN (%Ld)',
        $this->resourceIDs);
    }

    if ($this->leaseIDs) {
      $where[] = qsprintf(
        $conn_r,
        'leaseID IN (%Ld)',
        $this->leaseIDs);
    }

    return $this->formatWhereClause($where);
  }

  private function buildOrderClause($conn_r) {
    return 'ORDER BY log.epoch DESC';
  }

}
