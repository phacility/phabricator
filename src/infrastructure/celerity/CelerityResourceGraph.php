<?php

final class CelerityResourceGraph extends AbstractDirectedGraph {

  private $resourceGraph = array();
  private $graphSet = false;

  protected function loadEdges(array $nodes) {
    if (!$this->graphSet) {
      throw new Exception(
        "Call setResourceGraph before loading the graph!"
      );
    }

    $graph = $this->getResourceGraph();
    $edges = array();
    foreach ($nodes as $node) {
      $edges[$node] = idx($graph, $node, array());
    }
    return $edges;
  }

  final public function setResourceGraph(array $graph) {
    $this->resourceGraph = $graph;
    $this->graphSet = true;
    return $this;
  }

  private function getResourceGraph() {
    return $this->resourceGraph;
  }
}
