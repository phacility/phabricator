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

final class PonderQuestionQuery extends PhabricatorOffsetPagedQuery {

  const ORDER_CREATED = 'order-created';
  const ORDER_HOTTEST = 'order-hottest';

  private $ids;
  private $phids;
  private $authorPHIDs;
  private $order = self::ORDER_CREATED;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withAuthorPHIDs(array $phids) {
    $this->authorPHIDs = $phids;
    return $this;
  }

  public function setOrder($order) {
    $this->order = $order;
    return $this;
  }

  public static function loadSingle($viewer, $id) {
    if (!$viewer) {
      throw new Exception("Must set viewer when calling loadSingle");
    }

    return idx(id(new PonderQuestionQuery())
      ->withIDs(array($id))
      ->execute(), $id);
  }

  public static function loadSingleByPHID($viewer, $phid) {
    if (!$viewer) {
      throw new Exception("Must set viewer when calling loadSingle");
    }

    return array_shift(id(new PonderQuestionQuery())
      ->withPHIDs(array($phid))
      ->execute());
  }

  private function buildWhereClause(AphrontDatabaseConnection $conn_r) {
    $where = array();

    if ($this->ids) {
      $where[] = qsprintf($conn_r, 'q.id IN (%Ld)', $this->ids);
    }

    if ($this->phids) {
      $where[] = qsprintf($conn_r, 'q.phid IN (%Ls)', $this->phids);
    }

    if ($this->authorPHIDs) {
      $where[] = qsprintf($conn_r, 'q.authorPHID IN (%Ls)', $this->authorPHIDs);
    }

    return $this->formatWhereClause($where);
  }

  private function buildOrderByClause(AphrontDatabaseConnection $conn_r) {
    switch ($this->order) {
      case self::ORDER_HOTTEST:
        return qsprintf($conn_r, 'ORDER BY q.heat DESC, q.id DESC');
      case self::ORDER_CREATED:
        return qsprintf($conn_r, 'ORDER BY q.id DESC');
      default:
        throw new Exception("Unknown order '{$this->order}'!");
    }
  }

  public function execute() {
    $question = new PonderQuestion();
    $conn_r = $question->establishConnection('r');

    $where = $this->buildWhereClause($conn_r);
    $order_by = $this->buildOrderByClause($conn_r);
    $limit = $this->buildLimitClause($conn_r);

    return $question->loadAllFromArray(
      queryfx_all(
        $conn_r,
        'SELECT q.* FROM %T q %Q %Q %Q',
        $question->getTableName(),
        $where,
        $order_by,
        $limit));
  }


}
