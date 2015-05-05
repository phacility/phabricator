<?php

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
  private $destPHIDs;
  private $edgeTypes;
  private $resultSet;

  const ORDER_OLDEST_FIRST = 'order:oldest';
  const ORDER_NEWEST_FIRST = 'order:newest';
  private $order = self::ORDER_NEWEST_FIRST;

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
   * Find edges terminating at one or more destination PHIDs.
   *
   * @param list List of destination PHIDs.
   * @return this
   *
   */
  public function withDestinationPHIDs(array $dest_phids) {
    $this->destPHIDs = $dest_phids;
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
   * Configure the order edge results are returned in.
   *
   * @param const Order constant.
   * @return this
   *
   * @task config
   */
  public function setOrder($order) {
    $this->order = $order;
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
   * Convenience method for loading a single edge's metadata for
   * a given source, destination, and edge type. Returns null
   * if the edge does not exist or does not have metadata. Builds
   * and immediately executes a full query.
   *
   * @param phid  Source PHID.
   * @param const Edge type.
   * @param phid  Destination PHID.
   * @return wild Edge annotation (or null).
   */
  public static function loadSingleEdgeData($src_phid, $edge_type, $dest_phid) {
    $edges = id(new PhabricatorEdgeQuery())
      ->withSourcePHIDs(array($src_phid))
      ->withEdgeTypes(array($edge_type))
      ->withDestinationPHIDs(array($dest_phid))
      ->needEdgeData(true)
      ->execute();

    if (isset($edges[$src_phid][$edge_type][$dest_phid]['data'])) {
      return $edges[$src_phid][$edge_type][$dest_phid]['data'];
    }
    return null;
  }


  /**
   * Load specified edges.
   *
   * @task exec
   */
  public function execute() {
    if (!$this->sourcePHIDs) {
      throw new Exception(
      'You must use withSourcePHIDs() to query edges.');
    }

    $sources = phid_group_by_type($this->sourcePHIDs);

    $result = array();

    // When a query specifies types, make sure we return data for all queried
    // types.
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
              phutil_json_decode($row['data']),
              'data');
          }
        }
        foreach ($edges as $key => $edge) {
          $edges[$key]['data'] = idx($data_map, $edge['dataID'], array());
        }
      }

      foreach ($edges as $edge) {
        $result[$edge['src']][$edge['type']][$edge['dst']] = $edge;
      }
    }

    $this->resultSet = $result;
    return $result;
  }


  /**
   * Convenience function for selecting edge destination PHIDs after calling
   * execute().
   *
   * Returns a flat list of PHIDs matching the provided source PHID and type
   * filters. By default, the filters are empty so all PHIDs will be returned.
   * For example, if you're doing a batch query from several sources, you might
   * write code like this:
   *
   *   $query = new PhabricatorEdgeQuery();
   *   $query->setViewer($viewer);
   *   $query->withSourcePHIDs(mpull($objects, 'getPHID'));
   *   $query->withEdgeTypes(array($some_type));
   *   $query->execute();
   *
   *   // Gets all of the destinations.
   *   $all_phids = $query->getDestinationPHIDs();
   *   $handles = id(new PhabricatorHandleQuery())
   *     ->setViewer($viewer)
   *     ->withPHIDs($all_phids)
   *     ->execute();
   *
   *   foreach ($objects as $object) {
   *     // Get all of the destinations for the given object.
   *     $dst_phids = $query->getDestinationPHIDs(array($object->getPHID()));
   *     $object->attachHandles(array_select_keys($handles, $dst_phids));
   *   }
   *
   * @param list? List of PHIDs to select, or empty to select all.
   * @param list? List of edge types to select, or empty to select all.
   * @return list<phid> List of matching destination PHIDs.
   */
  public function getDestinationPHIDs(
    array $src_phids = array(),
    array $types = array()) {
    if ($this->resultSet === null) {
      throw new Exception(
        'You must execute() a query before you you can getDestinationPHIDs().');
    }

    $result_phids = array();

    $set = $this->resultSet;
    if ($src_phids) {
      $set = array_select_keys($set, $src_phids);
    }

    foreach ($set as $src => $edges_by_type) {
      if ($types) {
        $edges_by_type = array_select_keys($edges_by_type, $types);
      }

      foreach ($edges_by_type as $edges) {
        foreach ($edges as $edge_phid => $edge) {
          $result_phids[$edge_phid] = true;
        }
      }
    }

    return array_keys($result_phids);
  }


/* -(  Internals  )---------------------------------------------------------- */


  /**
   * @task internal
   */
  protected function buildWhereClause(AphrontDatabaseConnection $conn_r) {
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

    if ($this->destPHIDs) {
      // potentially complain if $this->edgeType was not set
      $where[] = qsprintf(
        $conn_r,
        'edge.dst IN (%Ls)',
        $this->destPHIDs);
    }

    return $this->formatWhereClause($where);
  }


  /**
   * @task internal
   */
  private function buildOrderClause($conn_r) {
    if ($this->order == self::ORDER_NEWEST_FIRST) {
      return 'ORDER BY edge.dateCreated DESC, edge.seq DESC';
    } else {
      return 'ORDER BY edge.dateCreated ASC, edge.seq ASC';
    }
  }

}
