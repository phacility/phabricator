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
 * Load object edges created by @{class:PhabricatorEdgeEditor}.
 *
 *   name=Querying Edges
 *   $src  = $earth_phid;
 *   $type = PhabricatorEdgeConfig::TYPE_BODY_HAS_SATELLITE;
 *
 *   // Load the earth's satellites.
 *   $satellite_edges = id(new PhabricatorEdgeQuery())
 *     ->withSourcePHIDs(array($src))
 *     ->withEdgeTypes(array($type))
 *     ->execute();
 *
 * For more information on edges, see @{article:Using Edges}.
 *
 * @task config   Configuring the Query
 * @task exec     Executing the Query
 * @task internal Internal
 */
final class PhabricatorEdgeQuery extends PhabricatorQuery {

  private $sourcePHIDs;
  private $edgeTypes;

  private $needEdgeData;


/* -(  Configuring the Query  )---------------------------------------------- */


  /**
   * Find edges originating at one or more source PHIDs. You MUST provide this
   * to execute an edge query.
   *
   * @param list List of source PHIDs.
   * @return this
   *
   * @task config
   */
  public function withSourcePHIDs(array $source_phids) {
    $this->sourcePHIDs = $source_phids;
    return $this;
  }


  /**
   * Find edges of specific types.
   *
   * @param list List of PhabricatorEdgeConfig type constants.
   * @return this
   *
   * @task config
   */
  public function withEdgeTypes(array $types) {
    $this->edgeTypes = $types;
    return $this;
  }


  /**
   * When loading edges, also load edge data.
   *
   * @param bool True to load edge data.
   * @return this
   *
   * @task config
   */
  public function needEdgeData($need) {
    $this->needEdgeData = $need;
    return $this;
  }


/* -(  Executing the Query  )------------------------------------------------ */


  /**
   * Convenience method for loading destination PHIDs with one source and one
   * edge type. Equivalent to building a full query, but simplifies a common
   * use case.
   *
   * @param phid  Source PHID.
   * @param const Edge type.
   * @return list<phid> List of destination PHIDs.
   */
  public static function loadDestinationPHIDs($src_phid, $edge_type) {
    $edges = id(new PhabricatorEdgeQuery())
      ->withSourcePHIDs(array($src_phid))
      ->withEdgeTypes(array($edge_type))
      ->execute();
    return array_keys($edges[$src_phid][$edge_type]);
  }


  /**
   * Load specified edges.
   *
   * @task exec
   */
  public function execute() {
    if (!$this->sourcePHIDs) {
      throw new Exception(
      "You must use withSourcePHIDs() to query edges.");
    }

    $sources = phid_group_by_type($this->sourcePHIDs);

    $result = array();

    // When a query specifies types, make sure we return data for all queried
    // types. This is mostly to make sure PhabricatorLiskDAO->attachEdges()
    // gets some data, so that getEdges() doesn't throw later.
    if ($this->edgeTypes) {
      foreach ($this->sourcePHIDs as $phid) {
        foreach ($this->edgeTypes as $type) {
          $result[$phid][$type] = array();
        }
      }
    }

    foreach ($sources as $type => $phids) {
      $conn_r = PhabricatorEdgeConfig::establishConnection($type, 'r');

      $where = $this->buildWhereClause($conn_r);
      $order = $this->buildOrderClause($conn_r);

      $edges = queryfx_all(
        $conn_r,
        'SELECT edge.* FROM %T edge %Q %Q',
        PhabricatorEdgeConfig::TABLE_NAME_EDGE,
        $where,
        $order);

      if ($this->needEdgeData) {
        $data_ids = array_filter(ipull($edges, 'dataID'));
        $data_map = array();
        if ($data_ids) {
          $data_rows = queryfx_all(
            $conn_r,
            'SELECT edgedata.* FROM %T edgedata WHERE id IN (%Ld)',
            PhabricatorEdgeConfig::TABLE_NAME_EDGEDATA,
            $data_ids);
          foreach ($data_rows as $row) {
            $data_map[$row['id']] = idx(
              json_decode($row['data'], true),
              'data');
          }
        }
        foreach ($edges as $key => $edge) {
          $edges[$key]['data'] = idx($data_map, $edge['dataID']);
        }
      }

      foreach ($edges as $edge) {
        $result[$edge['src']][$edge['type']][$edge['dst']] = $edge;
      }
    }

    return $result;
  }


/* -(  Internals  )---------------------------------------------------------- */


  /**
   * @task internal
   */
  private function buildWhereClause($conn_r) {
    $where = array();

    if ($this->sourcePHIDs) {
      $where[] = qsprintf(
        $conn_r,
        'edge.src IN (%Ls)',
        $this->sourcePHIDs);
    }

    if ($this->edgeTypes) {
      $where[] = qsprintf(
        $conn_r,
        'edge.type IN (%Ls)',
        $this->edgeTypes);
    }

    return $this->formatWhereClause($where);
  }


  /**
   * @task internal
   */
  private function buildOrderClause($conn_r) {
    return 'ORDER BY edge.dateCreated DESC, edge.seq ASC';
  }

}
