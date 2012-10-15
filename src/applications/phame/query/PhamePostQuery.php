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
final class PhamePostQuery extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $blogPHIDs;
  private $bloggerPHIDs;
  private $phameTitles;
  private $visibility;
  private $phids;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withBloggerPHIDs(array $blogger_phids) {
    $this->bloggerPHIDs = $blogger_phids;
    return $this;
  }

  public function withBlogPHIDs(array $blog_phids) {
    $this->blogPHIDs = $blog_phids;
    return $this;
  }

  public function withPhameTitles(array $phame_titles) {
    $this->phameTitles = $phame_titles;
    return $this;
  }

  public function withVisibility($visibility) {
    $this->visibility = $visibility;
    return $this;
  }

  protected function loadPage() {
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

    if ($posts) {
      // We require these to do visibility checks, so load them unconditionally.
      $blog_phids = mpull($posts, 'getBlogPHID');
      $blogs = id(new PhameBlogQuery())
        ->setViewer($this->getViewer())
        ->withPHIDs($blog_phids)
        ->execute();
      $blogs = mpull($blogs, null, 'getPHID');
      foreach ($posts as $post) {
        if (isset($blogs[$post->getBlogPHID()])) {
          $post->setBlog($blogs[$post->getBlogPHID()]);
        }
      }
    }

    return $posts;
  }

  private function buildWhereClause(AphrontDatabaseConnection $conn_r) {
    $where = array();

    if ($this->ids) {
      $where[] = qsprintf(
        $conn_r,
        'p.id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids) {
      $where[] = qsprintf(
        $conn_r,
        'p.phid IN (%Ls)',
        $this->phids);
    }

    if ($this->bloggerPHIDs) {
      $where[] = qsprintf(
        $conn_r,
        'p.bloggerPHID IN (%Ls)',
        $this->bloggerPHIDs);
    }

    if ($this->phameTitles) {
      $where[] = qsprintf(
        $conn_r,
        'p.phameTitle IN (%Ls)',
        $this->phameTitles);
    }

    if ($this->visibility !== null) {
      $where[] = qsprintf(
        $conn_r,
        'p.visibility = %d',
        $this->visibility);
    }

    if ($this->blogPHIDs) {
      $where[] = qsprintf(
        $conn_r,
        'p.blogPHID in (%Ls)',
        $this->blogPHIDs);
    }

    $where[] = $this->buildPagingClause($conn_r);

    return $this->formatWhereClause($where);
  }

}
