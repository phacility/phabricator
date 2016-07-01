<?php

abstract class PhabricatorObjectGraph
  extends AbstractDirectedGraph {

  private $viewer;
  private $edges = array();
  private $seedPHID;
  private $objects;

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

  abstract protected function getEdgeTypes();
  abstract protected function getParentEdgeType();
  abstract protected function newQuery();
  abstract protected function newTableRow($phid, $object, $trace);
  abstract protected function newTable(AphrontTableView $table);

  final public function setSeedPHID($phid) {
    $this->seedPHID = $phid;

    return $this->addNodes(
      array(
        '<seed>' => array($phid),
      ));
  }

  final public function isEmpty() {
    return (count($this->getNodes()) <= 2);
  }

  final public function getEdges($type) {
    return idx($this->edges, $type, array());
  }

  final protected function loadEdges(array $nodes) {
    $edge_types = $this->getEdgeTypes();

    $query = id(new PhabricatorEdgeQuery())
      ->withSourcePHIDs($nodes)
      ->withEdgeTypes($edge_types);

    $query->execute();

    $map = array();
    foreach ($nodes as $node) {
      foreach ($edge_types as $edge_type) {
        $dst_phids = $query->getDestinationPHIDs(
          array($node),
          array($edge_type));

        $this->edges[$edge_type][$node] = $dst_phids;
        foreach ($dst_phids as $dst_phid) {
          $map[$node][] = $dst_phid;
        }
      }

      $map[$node] = array_values(array_fuse($map[$node]));
    }

    return $map;
  }

  final public function newGraphTable() {
    $viewer = $this->getViewer();

    $ancestry = $this->getEdges($this->getParentEdgeType());

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
    foreach ($ancestry as $phid => $ignored) {
      $object = idx($objects, $phid);
      $rows[] = $this->newTableRow($phid, $object, $traces[$ii++]);

      if ($phid == $this->seedPHID) {
        $rowc[] = 'highlighted';
      } else {
        $rowc[] = null;
      }
    }

    $table = id(new AphrontTableView($rows))
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
