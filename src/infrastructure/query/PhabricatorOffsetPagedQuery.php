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

/**
 * A query class which uses offset/limit paging. Provides logic and accessors
 * for offsets and limits.
 */
abstract class PhabricatorOffsetPagedQuery extends PhabricatorQuery {

  private $offset;
  private $limit;

  final public function setOffset($offset) {
    $this->offset = $offset;
    return $this;
  }

  final public function setLimit($limit) {
    $this->limit = $limit;
    return $this;
  }

  final public function getOffset() {
    return $this->offset;
  }

  final public function getLimit() {
    return $this->limit;
  }

  protected function buildLimitClause(AphrontDatabaseConnection $conn_r) {
    if ($this->limit && $this->offset) {
      return qsprintf($conn_r, 'LIMIT %d, %d', $this->offset, $this->limit);
    } else if ($this->limit) {
      return qsprintf($conn_r, 'LIMIT %d', $this->limit);
    } else if ($this->offset) {
      return qsprintf($conn_r, 'LIMIT %d, %d', $this->offset, PHP_INT_MAX);
    } else {
      return '';
    }
  }

  final public function executeWithOffsetPager(AphrontPagerView $pager) {
    $this->setLimit($pager->getPageSize() + 1);
    $this->setOffset($pager->getOffset());

    $results = $this->execute();

    return $pager->sliceResults($results);
  }

}
