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

  private $id;
  private $phids;
  private $authorPHID;
  private $orderHottest;
  private $orderNewest;

  public function withID($qid) {
    $this->id = $qid;
    return $this;
  }

  public function withPHID($phid) {
    $this->phids = array($phid);
    return $this;
  }

  public function withPHIDs($phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withAuthorPHID($phid) {
    $this->authorPHID = $phid;
    return $this;
  }

  public function orderByHottest($usethis) {
    $this->orderHottest = $usethis;
    return $this;
  }

  public function orderByNewest($usethis) {
    $this->orderNewest = $usethis;
    return $this;
  }

  public static function loadHottest($viewer, $offset, $count) {
    if (!$viewer) {
      throw new Exception("Must set viewer when calling loadHottest");
    }

    return id(new PonderQuestionQuery())
      ->setOffset($offset)
      ->setLimit($count)
      ->orderByHottest(true)
      ->execute();
  }

  public static function loadByAuthor($viewer, $author_phid, $offset, $count) {
    if (!$viewer) {
      throw new Exception("Must set viewer when calling loadByAuthor");
    }

    return id(new PonderQuestionQuery())
      ->withAuthorPHID($author_phid)
      ->setOffset($offset)
      ->setLimit($count)
      ->orderByNewest(true)
      ->execute();
  }

  public static function loadSingle($viewer, $id) {
    if (!$viewer) {
      throw new Exception("Must set viewer when calling loadSingle");
    }

    return idx(id(new PonderQuestionQuery())
               ->withID($id)
               ->execute(), $id);
  }

  public static function loadSingleByPHID($viewer, $phid) {
    if (!$viewer) {
      throw new Exception("Must set viewer when calling loadSingle");
    }

    return array_shift(id(new PonderQuestionQuery())
      ->withPHID($phid)
      ->execute());
  }

  private function buildWhereClause($conn_r) {
    $where = array();
    if ($this->id) {
      $where[] = qsprintf($conn_r, '(id = %d)', $this->id);
    }
    if ($this->phids) {
      $where[] = qsprintf($conn_r, '(phid in (%Ls))', $this->phids);
    }
    if ($this->authorPHID) {
      $where[] = qsprintf($conn_r, '(authorPHID = %s)', $this->authorPHID);
    }

    return ($where ? 'WHERE ' . implode(' AND ', $where) : '');
  }

  private function buildOrderByClause($conn_r) {
    $order = array();
    if ($this->orderHottest) {
      $order[] = qsprintf($conn_r, 'heat DESC');
    }
    if ($this->orderNewest) {
      $order[] = qsprintf($conn_r, 'id DESC');
    }

    if (count($order) == 0) {
      $order[] = qsprintf($conn_r, 'id ASC');
    }

    return ($order ? 'ORDER BY ' . implode(', ', $order) : '');
  }

  public function execute() {
    $question = new PonderQuestion();
    $conn_r = $question->establishConnection('r');

    $select = qsprintf(
      $conn_r,
      'SELECT r.* FROM %T r',
      $question->getTableName());

    $where = $this->buildWhereClause($conn_r);
    $order_by = $this->buildOrderByClause($conn_r);
    $limit = $this->buildLimitClause($conn_r);

    return $question->loadAllFromArray(
      queryfx_all(
        $conn_r,
        '%Q %Q %Q %Q',
        $select,
        $where,
        $order_by,
        $limit));
  }


}
