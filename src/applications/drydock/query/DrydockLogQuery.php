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

  const ORDER_EPOCH   = 'order-epoch';
  const ORDER_ID      = 'order-id';

  private $resourceIDs;
  private $leaseIDs;
  private $afterID;
  private $order = self::ORDER_EPOCH;

  public function withResourceIDs(array $ids) {
    $this->resourceIDs = $ids;
    return $this;
  }

  public function withLeaseIDs(array $ids) {
    $this->leaseIDs = $ids;
    return $this;
  }

  public function setOrder($order) {
    $this->order = $order;
    return $this;
  }

  public function withAfterID($id) {
    $this->afterID = $id;
    return $this;
  }

  public function execute() {
    $table = new DrydockLog();
    $conn_r = $table->establishConnection('r');

    $data = queryfx_all(
      $conn_r,
      'SELECT log.* FROM %T log %Q %Q %Q',
      $table->getTableName(),
      $this->buildWhereClause($conn_r),
      $this->buildOrderClause($conn_r),
      $this->buildLimitClause($conn_r));

    return $table->loadAllFromArray($data);
  }

  private function buildWhereClause(AphrontDatabaseConnection $conn_r) {
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

    if ($this->afterID) {
      $where[] = qsprintf(
        $conn_r,
        'id > %d',
        $this->afterID);
    }

    return $this->formatWhereClause($where);
  }

  private function buildOrderClause(AphrontDatabaseConnection $conn_r) {
    switch ($this->order) {
      case self::ORDER_EPOCH:
        return 'ORDER BY log.epoch DESC';
      case self::ORDER_ID:
        return 'ORDER BY id ASC';
      default:
        throw new Exception("Unknown order '{$this->order}'!");
    }
  }

}
