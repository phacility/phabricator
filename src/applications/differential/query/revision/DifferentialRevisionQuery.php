<?php

/*
 * Copyright 2011 Facebook, Inc.
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
 * Flexible query API for Differential revisions. Example:
 *
 *   // Load open revisions
 *   $revisions = id(new DifferentialRevisionQuery())
 *     ->withStatus(DifferentialRevisionQuery::STATUS_OPEN)
 *     ->execute();
 *
 * @task config   Query Configuration
 * @task exec     Query Execution
 * @task internal Internals
 */
final class DifferentialRevisionQuery {

  // TODO: Replace DifferentialRevisionListData with this class.

  private $pathIDs = array();

  private $status       = 'status-any';
  const STATUS_ANY      = 'status-any';
  const STATUS_OPEN     = 'status-open';

  private $order            = 'order-modified';
  const ORDER_MODIFIED      = 'order-modified';
  const ORDER_CREATED       = 'order-created';
  /**
   * This is essentially a denormalized copy of the revision modified time that
   * should perform better for path queries with a LIMIT. Critically, when you
   * browse "/", every revision in that repository for all time will match so
   * the query benefits from being able to stop before fully materializing the
   * result set.
   */
  const ORDER_PATH_MODIFIED = 'order-path-modified';

  private $limit  = 1000;
  private $offset = 0;

  private $needRelationships = false;


/* -(  Query Configuration  )------------------------------------------------ */


  /**
   * Filter results to revisions which affect a Diffusion path ID in a given
   * repository. You can call this multiple times to select revisions for
   * several paths.
   *
   * @param int Diffusion repository ID.
   * @param int Diffusion path ID.
   * @return this
   * @task config
   */
  public function withPath($repository_id, $path_id) {
    $this->pathIDs[] = array(
      'repositoryID' => $repository_id,
      'pathID'       => $path_id,
    );
    return $this;
  }


  /**
   * Filter results to revisions with a given status. Provide a class constant,
   * such as ##DifferentialRevisionQuery::STATUS_OPEN##.
   *
   * @param const Class STATUS constant, like STATUS_OPEN.
   * @return this
   * @task config
   */
  public function withStatus($status_constant) {
    $this->status = $status_constant;
    return $this;
  }


  /**
   * Set result ordering. Provide a class constant, such as
   * ##DifferentialRevisionQuery::ORDER_CREATED##.
   *
   * @task config
   */
  public function setOrder($order_constant) {
    $this->order = $order_constant;
    return $this;
  }


  /**
   * Set result limit. If unspecified, defaults to 1000.
   *
   * @param int Result limit.
   * @return this
   * @task config
   */
  public function setLimit($limit) {
    $this->limit = $limit;
    return $this;
  }


  /**
   * Set result offset. If unspecified, defaults to 0.
   *
   * @param int Result offset.
   * @return this
   * @task config
   */
  public function setOffset($offset) {
    $this->offset = $offset;
    return $this;
  }


  /**
   * Set whether or not the query will load and attach relationships.
   *
   * @param bool True to load and attach relationships.
   * @return this
   * @task config
   */
  public function needRelationships($need_relationships) {
    $this->needRelationships = $need_relationships;
    return $this;
  }


/* -(  Query Execution  )---------------------------------------------------- */


  /**
   * Execute the query as configured, returning matching
   * @{class:DifferentialRevision} objects.
   *
   * @return list List of matching DifferentialRevision objects.
   * @task exec
   */
  public function execute() {
    $table = new DifferentialRevision();
    $conn_r = $table->establishConnection('r');

    $select = qsprintf(
      $conn_r,
      'SELECT r.* FROM %T r',
      $table->getTableName());

    $joins = $this->buildJoinsClause($conn_r);
    $where = $this->buildWhereClause($conn_r);
    $group_by = $this->buildGroupByClause($conn_r);
    $order_by = $this->buildOrderByClause($conn_r);

    $limit = qsprintf(
      $conn_r,
      'LIMIT %d, %d',
      (int)$this->offset,
      $this->limit);

    $data = queryfx_all(
      $conn_r,
      '%Q %Q %Q %Q %Q %Q',
      $select,
      $joins,
      $where,
      $group_by,
      $order_by,
      $limit);

    $revisions = $table->loadAllFromArray($data);

    if ($revisions && $this->needRelationships) {
      $relationships = queryfx_all(
        $conn_r,
        'SELECT * FROM %T WHERE revisionID in (%Ld) ORDER BY sequence',
        DifferentialRevision::RELATIONSHIP_TABLE,
        mpull($revisions, 'getID'));
      $relationships = igroup($relationships, 'revisionID');
      foreach ($revisions as $revision) {
        $revision->attachRelationships(
          idx(
            $relationships,
            $revision->getID(),
            array()));
      }
    }

    return $revisions;
  }


/* -(  Internals  )---------------------------------------------------------- */


  /**
   * @task internal
   */
  private function buildJoinsClause($conn_r) {
    $joins = array();
    if ($this->pathIDs) {
      $path_table = new DifferentialAffectedPath();
      $joins[] = qsprintf(
        $conn_r,
        'JOIN %T p ON p.revisionID = r.id',
        $path_table->getTableName());
    }

    $joins = implode(' ', $joins);

    return $joins;
  }


  /**
   * @task internal
   */
  private function buildWhereClause($conn_r) {
    $where = array();

    if ($this->pathIDs) {
      $path_clauses = array();
      $repo_info = igroup($this->pathIDs, 'repositoryID');
      foreach ($repo_info as $repository_id => $paths) {
        $path_clauses[] = qsprintf(
          $conn_r,
          '(repositoryID = %d AND pathID IN (%Ld))',
          $repository_id,
          ipull($paths, 'pathID'));
      }
      $path_clauses = '('.implode(' OR ', $path_clauses).')';
      $where[] = $path_clauses;
    }

    switch ($this->status) {
      case self::STATUS_ANY:
        break;
      case self::STATUS_OPEN:
        $where[] = qsprintf(
          $conn_r,
          'status IN (%Ld)',
          array(
            DifferentialRevisionStatus::NEEDS_REVIEW,
            DifferentialRevisionStatus::NEEDS_REVISION,
            DifferentialRevisionStatus::ACCEPTED,
          ));
        break;
      default:
        throw new Exception(
          "Unknown revision status filter constant '{$this->status}'!");
    }

    if ($where) {
      $where = 'WHERE '.implode(' AND ', $where);
    } else {
      $where = '';
    }

    return $where;
  }


  /**
   * @task internal
   */
  private function buildGroupByClause($conn_r) {
    $needs_distinct = (count($this->pathIDs) > 1);

    if ($needs_distinct) {
      return 'GROUP BY r.id';
    } else {
      return '';
    }
  }


  /**
   * @task internal
   */
  private function buildOrderByClause($conn_r) {
    switch ($this->order) {
      case self::ORDER_MODIFIED:
        return 'ORDER BY r.dateModified DESC';
      case self::ORDER_CREATED:
        return 'ORDER BY r.dateCreated DESC';
      case self::ORDER_PATH_MODIFIED:
        if (!$this->pathIDs) {
          throw new Exception(
            "To use ORDER_PATH_MODIFIED, you must specify withPath().");
        }
        return 'ORDER BY p.epoch DESC';
      default:
        throw new Exception("Unknown query order constant '{$this->order}'.");
    }
  }


}
