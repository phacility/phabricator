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
final class PhameBlogQuery extends PhabricatorOffsetPagedQuery {

  private $phids;
  private $domain;
  private $needBloggers;

  public function withPHIDs($phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withDomain($domain) {
    $this->domain = $domain;
    return $this;
  }

  public function needBloggers($need_bloggers) {
    $this->needBloggers = $need_bloggers;
    return $this;
  }

  public function execute() {
    $table  = new PhameBlog();
    $conn_r = $table->establishConnection('r');

    $where_clause = $this->buildWhereClause($conn_r);
    $order_clause = $this->buildOrderClause($conn_r);
    $limit_clause = $this->buildLimitClause($conn_r);

    $data = queryfx_all(
      $conn_r,
      'SELECT * FROM %T b %Q %Q %Q',
      $table->getTableName(),
      $where_clause,
      $order_clause,
      $limit_clause);

    $blogs = $table->loadAllFromArray($data);

    if ($blogs) {
      if ($this->needBloggers) {
        $this->loadBloggers($blogs);
      }
    }

    return $blogs;
  }

  private function loadBloggers(array $blogs) {
    assert_instances_of($blogs, 'PhameBlog');
    $blog_phids = mpull($blogs, 'getPHID');

    $edge_types = array(PhabricatorEdgeConfig::TYPE_BLOG_HAS_BLOGGER);

    $query = new PhabricatorEdgeQuery();
    $query->withSourcePHIDs($blog_phids)
          ->withEdgeTypes($edge_types)
          ->execute();

    $all_blogger_phids = $query->getDestinationPHIDs(
      $blog_phids,
      $edge_types
    );

    $handles = id(new PhabricatorObjectHandleData($all_blogger_phids))
      ->loadHandles();

    foreach ($blogs as $blog) {
      $blogger_phids = $query->getDestinationPHIDs(
        array($blog->getPHID()),
        $edge_types
      );
      $blog->attachBloggers(array_select_keys($handles, $blogger_phids));
    }
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

    if ($this->domain) {
      $where[] = qsprintf(
        $conn_r,
        'domain = %s',
        $this->domain
      );
    }

    return $this->formatWhereClause($where);
  }

  private function buildOrderClause($conn_r) {
    return 'ORDER BY id DESC';
  }
}
