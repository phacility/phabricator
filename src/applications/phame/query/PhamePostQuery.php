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
 * @group phame
 */
final class PhamePostQuery extends PhabricatorOffsetPagedQuery {

  private $bloggerPHID;
  private $withoutBloggerPHID;
  private $phameTitle;
  private $visibility;
  private $phids;

  /**
   * Mutually exlusive with @{method:withoutBloggerPHID}.
   *
   * @{method:withBloggerPHID} wins because being positive and inclusive is
   * cool.
   */
  public function withBloggerPHID($blogger_phid) {
    $this->bloggerPHID = $blogger_phid;
    return $this;
  }
  public function withoutBloggerPHID($blogger_phid) {
    $this->withoutBloggerPHID = $blogger_phid;
    return $this;
  }

  public function withPhameTitle($phame_title) {
    $this->phameTitle = $phame_title;
    return $this;
  }

  public function withVisibility($visibility) {
    $this->visibility = $visibility;
    return $this;
  }

  public function withPHIDs($phids) {
    $this->phids = $phids;
    return $this;
  }

  public function execute() {
    $table  = new PhamePost();
    $conn_r = $table->establishConnection('r');

    $where_clause = $this->buildWhereClause($conn_r);
    $order_clause = $this->buildOrderClause($conn_r);
    $limit_clause = $this->buildLimitClause($conn_r);

    $data = queryfx_all(
      $conn_r,
      'SELECT * FROM %T p %Q %Q %Q',
      $table->getTableName(),
      $where_clause,
      $order_clause,
      $limit_clause);

    $posts = $table->loadAllFromArray($data);

    return $posts;
  }

  private function buildWhereClause($conn_r) {
    $where = array();

    if ($this->phids) {
      $where[] = qsprintf(
        $conn_r,
        'phid IN (%Ls)',
        $this->phids
      );
    }

    if ($this->bloggerPHID) {
      $where[] = qsprintf(
        $conn_r,
        'bloggerPHID = %s',
        $this->bloggerPHID
      );
    } else if ($this->withoutBloggerPHID) {
      $where[] = qsprintf(
        $conn_r,
        'bloggerPHID != %s',
        $this->withoutBloggerPHID
      );
    }

    if ($this->phameTitle) {
      $where[] = qsprintf(
        $conn_r,
        'phameTitle = %s',
        $this->phameTitle
      );
    }

    if ($this->visibility !== null) {
      $where[] = qsprintf(
        $conn_r,
        'visibility = %d',
        $this->visibility
      );
    }

    return $this->formatWhereClause($where);
  }

  private function buildOrderClause($conn_r) {
    return 'ORDER BY datePublished DESC, id DESC';
  }
}
