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
 * Add and remove edges between objects. You can use
 * @{class:PhabricatorEdgeQuery} to load object edges. For more information
 * on edges, see @{article:Using Edges}.
 *
 *    name=Adding Edges
 *    $src  = $earth_phid;
 *    $type = PhabricatorEdgeConfig::TYPE_BODY_HAS_SATELLITE;
 *    $dst  = $moon_phid;
 *
 *    id(new PhabricatorEdgeEditor())
 *      ->addEdge($src, $type, $dst)
 *      ->setActor($user)
 *      ->save();
 *
 * @task edit     Editing Edges
 * @task cycles   Cycle Prevention
 * @task internal Internals
 */
final class PhabricatorEdgeEditor extends PhabricatorEditor {

  private $addEdges = array();
  private $remEdges = array();
  private $openTransactions = array();
  private $suppressEvents;


/* -(  Editing Edges  )------------------------------------------------------ */


  /**
   * Add a new edge (possibly also adding its inverse). Changes take effect when
   * you call @{method:save}. If the edge already exists, it will not be
   * overwritten. Removals queued with @{method:removeEdge} are executed before
   * adds, so the effect of removing and adding the same edge is to overwrite
   * any existing edge.
   *
   * The `$options` parameter accepts these values:
   *
   *   - `data` Optional, data to write onto the edge.
   *   - `inverse_data` Optional, data to write on the inverse edge. If not
   *     provided, `data` will be written.
   *
   * @param phid  Source object PHID.
   * @param const Edge type constant.
   * @param phid  Destination object PHID.
   * @param map   Options map (see documentation).
   * @return this
   *
   * @task edit
   */
  public function addEdge($src, $type, $dst, array $options = array()) {
    foreach ($this->buildEdgeSpecs($src, $type, $dst, $options) as $spec) {
      $this->addEdges[] = $spec;
    }
    return $this;
  }


  /**
   * Remove an edge (possibly also removing its inverse). Changes take effect
   * when you call @{method:save}. If an edge does not exist, the removal
   * will be ignored. Edges are added after edges are removed, so the effect of
   * a remove plus an add is to overwrite.
   *
   * @param phid  Source object PHID.
   * @param const Edge type constant.
   * @param phid  Destination object PHID.
   * @return this
   *
   * @task edit
   */
  public function removeEdge($src, $type, $dst) {
    foreach ($this->buildEdgeSpecs($src, $type, $dst) as $spec) {
      $this->remEdges[] = $spec;
    }
    return $this;
  }


  /**
   * Apply edge additions and removals queued by @{method:addEdge} and
   * @{method:removeEdge}. Note that transactions are opened, all additions and
   * removals are executed, and then transactions are saved. Thus, in some cases
   * it may be slightly more efficient to perform multiple edit operations
   * (e.g., adds followed by removals) if their outcomes are not dependent,
   * since transactions will not be held open as long.
   *
   * @return this
   * @task edit
   */
  public function save() {

    $cycle_types = $this->getPreventCyclesEdgeTypes();

    $locks = array();
    $caught = null;
    try {

      // NOTE: We write edge data first, before doing any transactions, since
      // it's OK if we just leave it hanging out in space unattached to
      // anything.
      $this->writeEdgeData();

      // If we're going to perform cycle detection, lock the edge type before
      // doing edits.
      if ($cycle_types) {
        $src_phids = ipull($this->addEdges, 'src');
        foreach ($cycle_types as $cycle_type) {
          $key = 'edge.cycle:'.$cycle_type;
          $locks[] = PhabricatorGlobalLock::newLock($key)->lock(15);
        }
      }

      static $id = 0;
      $id++;

      $this->sendEvent($id, PhabricatorEventType::TYPE_EDGE_WILLEDITEDGES);

      // NOTE: Removes first, then adds, so that "remove + add" is a useful
      // operation meaning "overwrite".

      $this->executeRemoves();
      $this->executeAdds();

      foreach ($cycle_types as $cycle_type) {
        $this->detectCycles($src_phids, $cycle_type);
      }

      $this->sendEvent($id, PhabricatorEventType::TYPE_EDGE_DIDEDITEDGES);

      $this->saveTransactions();
    } catch (Exception $ex) {
      $caught = $ex;
    }


    if ($caught) {
      $this->killTransactions();
    }

    foreach ($locks as $lock) {
      $lock->unlock();
    }

    if ($caught) {
      throw $caught;
    }
  }


/* -(  Internals  )---------------------------------------------------------- */


  /**
   * Build the specification for an edge operation, and possibly build its
   * inverse as well.
   *
   * @task internal
   */
  private function buildEdgeSpecs($src, $type, $dst, array $options = array()) {
    $data = array();
    if (!empty($options['data'])) {
      $data['data'] = $options['data'];
    }

    $src_type = phid_get_type($src);
    $dst_type = phid_get_type($dst);

    $specs = array();
    $specs[] = array(
      'src'       => $src,
      'src_type'  => $src_type,
      'dst'       => $dst,
      'dst_type'  => $dst_type,
      'type'      => $type,
      'data'      => $data,
    );

    $inverse = PhabricatorEdgeConfig::getInverse($type);
    if ($inverse) {

      // If `inverse_data` is set, overwrite the edge data. Normally, just
      // write the same data to the inverse edge.
      if (array_key_exists('inverse_data', $options)) {
        $data['data'] = $options['inverse_data'];
      }

      $specs[] = array(
        'src'       => $dst,
        'src_type'  => $dst_type,
        'dst'       => $src,
        'dst_type'  => $src_type,
        'type'      => $inverse,
        'data'      => $data,
      );
    }

    return $specs;
  }


  /**
   * Write edge data.
   *
   * @task internal
   */
  private function writeEdgeData() {
    $adds = $this->addEdges;

    $writes = array();
    foreach ($adds as $key => $edge) {
      if ($edge['data']) {
        $writes[] = array($key, $edge['src_type'], json_encode($edge['data']));
      }
    }

    foreach ($writes as $write) {
      list($key, $src_type, $data) = $write;
      $conn_w = PhabricatorEdgeConfig::establishConnection($src_type, 'w');
      queryfx(
        $conn_w,
        'INSERT INTO %T (data) VALUES (%s)',
        PhabricatorEdgeConfig::TABLE_NAME_EDGEDATA,
        $data);
      $this->addEdges[$key]['data_id'] = $conn_w->getInsertID();
    }
  }


  /**
   * Add queued edges.
   *
   * @task internal
   */
  private function executeAdds() {
    $adds = $this->addEdges;
    $adds = igroup($adds, 'src_type');

    // Assign stable sequence numbers to each edge, so we have a consistent
    // ordering across edges by source and type.
    foreach ($adds as $src_type => $edges) {
      $edges_by_src = igroup($edges, 'src');
      foreach ($edges_by_src as $src => $src_edges) {
        $seq = 0;
        foreach ($src_edges as $key => $edge) {
          $src_edges[$key]['seq'] = $seq++;
          $src_edges[$key]['dateCreated'] = time();
        }
        $edges_by_src[$src] = $src_edges;
      }
      $adds[$src_type] = array_mergev($edges_by_src);
    }

    $inserts = array();
    foreach ($adds as $src_type => $edges) {
      $conn_w = PhabricatorEdgeConfig::establishConnection($src_type, 'w');
      $sql = array();
      foreach ($edges as $edge) {
        $sql[] = qsprintf(
          $conn_w,
          '(%s, %d, %s, %d, %d, %nd)',
          $edge['src'],
          $edge['type'],
          $edge['dst'],
          $edge['dateCreated'],
          $edge['seq'],
          idx($edge, 'data_id'));
      }
      $inserts[] = array($conn_w, $sql);
    }

    foreach ($inserts as $insert) {
      list($conn_w, $sql) = $insert;
      $conn_w->openTransaction();
      $this->openTransactions[] = $conn_w;

      foreach (array_chunk($sql, 256) as $chunk) {
        queryfx(
          $conn_w,
          'INSERT IGNORE INTO %T (src, type, dst, dateCreated, seq, dataID)
            VALUES %Q',
          PhabricatorEdgeConfig::TABLE_NAME_EDGE,
          implode(', ', $chunk));
      }
    }
  }


  /**
   * Remove queued edges.
   *
   * @task internal
   */
  private function executeRemoves() {
    $rems = $this->remEdges;
    $rems = igroup($rems, 'src_type');

    $deletes = array();
    foreach ($rems as $src_type => $edges) {
      $conn_w = PhabricatorEdgeConfig::establishConnection($src_type, 'w');
      $sql = array();
      foreach ($edges as $edge) {
        $sql[] = qsprintf(
          $conn_w,
          '(%s, %d, %s)',
          $edge['src'],
          $edge['type'],
          $edge['dst']);
      }
      $deletes[] = array($conn_w, $sql);
    }

    foreach ($deletes as $delete) {
      list($conn_w, $sql) = $delete;

      $conn_w->openTransaction();
      $this->openTransactions[] = $conn_w;

      foreach (array_chunk($sql, 256) as $chunk) {
        queryfx(
          $conn_w,
          'DELETE FROM %T WHERE (src, type, dst) IN (%Q)',
          PhabricatorEdgeConfig::TABLE_NAME_EDGE,
          implode(', ', $chunk));
      }
    }
  }


  /**
   * Save open transactions.
   *
   * @task internal
   */
  private function saveTransactions() {
    foreach ($this->openTransactions as $key => $conn_w) {
      $conn_w->saveTransaction();
      unset($this->openTransactions[$key]);
    }
  }

  private function killTransactions() {
    foreach ($this->openTransactions as $key => $conn_w) {
      $conn_w->killTransaction();
      unset($this->openTransactions[$key]);
    }
  }

  /**
   * Suppress edge edit events. This prevents listeners from making updates in
   * response to edits, and is primarily useful when performing migrations. You
   * should not normally need to use it.
   *
   * @param bool True to supress events related to edits.
   * @return this
   * @task internal
   */
  public function setSuppressEvents($suppress) {
    $this->suppressEvents = $suppress;
    return $this;
  }


  private function sendEvent($edit_id, $event_type) {
    if ($this->suppressEvents) {
      return;
    }

    $event = new PhabricatorEvent(
      $event_type,
      array(
        'id'    => $edit_id,
        'add'   => $this->addEdges,
        'rem'   => $this->remEdges,
      ));
    $event->setUser($this->getActor());
    PhutilEventEngine::dispatchEvent($event);
  }


/* -(  Cycle Prevention  )--------------------------------------------------- */


  /**
   * Get a list of all edge types which are being added, and which we should
   * prevent cycles on.
   *
   * @return list<const> List of edge types which should have cycles prevented.
   * @task cycle
   */
  private function getPreventCyclesEdgeTypes() {
    $edge_types = array();
    foreach ($this->addEdges as $edge) {
      $edge_types[$edge['type']] = true;
    }
    foreach ($edge_types as $type => $ignored) {
      if (!PhabricatorEdgeConfig::shouldPreventCycles($type)) {
        unset($edge_types[$type]);
      }
    }
    return array_keys($edge_types);
  }


  /**
   * Detect graph cycles of a given edge type. If the edit introduces a cycle,
   * a @{class:PhabricatorEdgeCycleException} is thrown with details.
   *
   * @return void
   * @task cycle
   */
  private function detectCycles(array $phids, $edge_type) {
    // For simplicity, we just seed the graph with the affected nodes rather
    // than seeding it with their edges. To do this, we just add synthetic
    // edges from an imaginary '<seed>' node to the known edges.


    $graph = id(new PhabricatorEdgeGraph())
      ->setEdgeType($edge_type)
      ->addNodes(
        array(
          '<seed>' => $phids,
        ))
      ->loadGraph();

    foreach ($phids as $phid) {
      $cycle = $graph->detectCycles($phid);
      if ($cycle) {
        throw new PhabricatorEdgeCycleException($edge_type, $cycle);
      }
    }
  }


}
