<?php

abstract class PhabricatorObjectGraph
  extends AbstractDirectedGraph {

  private $viewer;
  private $edges = array();
  private $edgeReach = array();
  private $seedPHID;
  private $objects;
  private $loadEntireGraph = false;
  private $limit;
  private $adjacent;

  public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  public function getViewer() {
    if (!$this->viewer) {
      throw new PhutilInvalidStateException('setViewer');
    }

    return $this->viewer;
  }

  public function setLimit($limit) {
    $this->limit = $limit;
    return $this;
  }

  public function getLimit() {
    return $this->limit;
  }

  final public function setRenderOnlyAdjacentNodes($adjacent) {
    $this->adjacent = $adjacent;
    return $this;
  }

  final public function getRenderOnlyAdjacentNodes() {
    return $this->adjacent;
  }

  abstract protected function getEdgeTypes();
  abstract protected function getParentEdgeType();
  abstract protected function newQuery();
  abstract protected function newTableRow($phid, $object, $trace);
  abstract protected function newTable(AphrontTableView $table);
  abstract protected function isClosed($object);

  protected function newEllipsisRow() {
    return array(
      '...',
    );
  }

  final public function setSeedPHID($phid) {
    $this->seedPHID = $phid;
    $this->edgeReach[$phid] = array_fill_keys($this->getEdgeTypes(), true);

    return $this->addNodes(
      array(
        '<seed>' => array($phid),
      ));
  }

  final public function getSeedPHID() {
    return $this->seedPHID;
  }

  final public function isEmpty() {
    return (count($this->getNodes()) <= 2);
  }

  final public function isOverLimit() {
    $limit = $this->getLimit();

    if (!$limit) {
      return false;
    }

    return (count($this->edgeReach) > $limit);
  }

  final public function getEdges($type) {
    $edges = idx($this->edges, $type, array());

    // Remove any nodes which we never reached. We can get these when loading
    // only part of the graph: for example, they point at other subtasks of
    // parents or other parents of subtasks.
    $nodes = $this->getNodes();
    foreach ($edges as $src => $dsts) {
      foreach ($dsts as $key => $dst) {
        if (!isset($nodes[$dst])) {
          unset($edges[$src][$key]);
        }
      }
    }

    return $edges;
  }

  final public function setLoadEntireGraph($load_entire_graph) {
    $this->loadEntireGraph = $load_entire_graph;
    return $this;
  }

  final public function getLoadEntireGraph() {
    return $this->loadEntireGraph;
  }

  final protected function loadEdges(array $nodes) {
    if ($this->isOverLimit()) {
      return array_fill_keys($nodes, array());
    }

    $edge_types = $this->getEdgeTypes();

    $query = id(new PhabricatorEdgeQuery())
      ->withSourcePHIDs($nodes)
      ->withEdgeTypes($edge_types);

    $query->execute();

    $whole_graph = $this->getLoadEntireGraph();

    $map = array();
    foreach ($nodes as $node) {
      $map[$node] = array();

      foreach ($edge_types as $edge_type) {
        $dst_phids = $query->getDestinationPHIDs(
          array($node),
          array($edge_type));

        $this->edges[$edge_type][$node] = $dst_phids;
        foreach ($dst_phids as $dst_phid) {
          if ($whole_graph || isset($this->edgeReach[$node][$edge_type])) {
            $map[$node][] = $dst_phid;
          }
          $this->edgeReach[$dst_phid][$edge_type] = true;
        }
      }

      $map[$node] = array_values(array_fuse($map[$node]));
    }

    return $map;
  }

  final public function newGraphTable() {
    $viewer = $this->getViewer();

    $ancestry = $this->getEdges($this->getParentEdgeType());

    $only_adjacent = $this->getRenderOnlyAdjacentNodes();
    if ($only_adjacent) {
      $adjacent = array(
        $this->getSeedPHID() => $this->getSeedPHID(),
      );

      foreach ($this->getEdgeTypes() as $edge_type) {
        $map = $this->getEdges($edge_type);
        $direct = idx($map, $this->getSeedPHID(), array());
        $adjacent += array_fuse($direct);
      }

      foreach ($ancestry as $key => $list) {
        if (!isset($adjacent[$key])) {
          unset($ancestry[$key]);
          continue;
        }

        foreach ($list as $list_key => $item) {
          if (!isset($adjacent[$item])) {
            unset($ancestry[$key][$list_key]);
          }
        }
      }
    }

    $objects = $this->newQuery()
      ->setViewer($viewer)
      ->withPHIDs(array_keys($ancestry))
      ->execute();
    $objects = mpull($objects, null, 'getPHID');

    $order = id(new PhutilDirectedScalarGraph())
      ->addNodes($ancestry)
      ->getTopographicallySortedNodes();

    $ancestry = array_select_keys($ancestry, $order);

    $traces = id(new PHUIDiffGraphView())
      ->renderGraph($ancestry);

    $ii = 0;
    $rows = array();
    $rowc = array();

    if ($only_adjacent) {
      $rows[] = $this->newEllipsisRow();
      $rowc[] = 'more';
    }

    foreach ($ancestry as $phid => $ignored) {
      $object = idx($objects, $phid);
      $rows[] = $this->newTableRow($phid, $object, $traces[$ii++]);

      $classes = array();
      if ($phid == $this->seedPHID) {
        $classes[] = 'highlighted';
      }

      if ($object) {
        if ($this->isClosed($object)) {
          $classes[] = 'closed';
        }
      }

      if ($classes) {
        $classes = implode(' ', $classes);
      } else {
        $classes = null;
      }

      $rowc[] = $classes;
    }

    if ($only_adjacent) {
      $rows[] = $this->newEllipsisRow();
      $rowc[] = 'more';
    }

    $table = id(new AphrontTableView($rows))
      ->setClassName('object-graph-table')
      ->setRowClasses($rowc);

    $this->objects = $objects;

    return $this->newTable($table);
  }

  final public function getReachableObjects($edge_type) {
    if ($this->objects === null) {
      throw new PhutilInvalidStateException('newGraphTable');
    }

    $graph = $this->getEdges($edge_type);

    $seen = array();
    $look = array($this->seedPHID);
    while ($look) {
      $phid = array_pop($look);

      $parents = idx($graph, $phid, array());
      foreach ($parents as $parent) {
        if (isset($seen[$parent])) {
          continue;
        }

        $seen[$parent] = $parent;
        $look[] = $parent;
      }
    }

    $reachable = array();
    foreach ($seen as $phid) {
      if ($phid == $this->seedPHID) {
        continue;
      }

      $object = idx($this->objects, $phid);
      if (!$object) {
        continue;
      }

      $reachable[] = $object;
    }

    return $reachable;
  }

}
