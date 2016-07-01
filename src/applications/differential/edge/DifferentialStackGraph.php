<?php

final class DifferentialStackGraph
  extends AbstractDirectedGraph {

  private $parentEdges = array();
  private $childEdges = array();

  public function setSeedRevision(DifferentialRevision $revision) {
    return $this->addNodes(
      array(
        '<seed>' => array($revision->getPHID()),
      ));
  }

  public function isEmpty() {
    return (count($this->getNodes()) <= 2);
  }

  public function getParentEdges() {
    return $this->parentEdges;
  }

  protected function loadEdges(array $nodes) {
    $query = id(new PhabricatorEdgeQuery())
      ->withSourcePHIDs($nodes)
      ->withEdgeTypes(
        array(
          DifferentialRevisionDependsOnRevisionEdgeType::EDGECONST,
          DifferentialRevisionDependedOnByRevisionEdgeType::EDGECONST,
        ));

    $query->execute();

    $map = array();
    foreach ($nodes as $node) {
      $parents = $query->getDestinationPHIDs(
        array($node),
        array(
          DifferentialRevisionDependsOnRevisionEdgeType::EDGECONST,
        ));

      $children = $query->getDestinationPHIDs(
        array($node),
        array(
          DifferentialRevisionDependedOnByRevisionEdgeType::EDGECONST,
        ));

      $this->parentEdges[$node] = $parents;
      $this->childEdges[$node] = $children;

      $map[$node] = array_values(array_fuse($parents) + array_fuse($children));
    }

    return $map;
  }

}
