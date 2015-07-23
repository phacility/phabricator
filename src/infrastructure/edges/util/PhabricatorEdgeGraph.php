<?php

final class PhabricatorEdgeGraph extends AbstractDirectedGraph {

  private $edgeType;

  public function setEdgeType($edge_type) {
    $this->edgeType = $edge_type;
    return $this;
  }

  protected function loadEdges(array $nodes) {
    if (!$this->edgeType) {
      throw new Exception(pht('Set edge type before loading graph!'));
    }

    $edges = id(new PhabricatorEdgeQuery())
      ->withSourcePHIDs($nodes)
      ->withEdgeTypes(array($this->edgeType))
      ->execute();

    $results = array_fill_keys($nodes, array());
    foreach ($edges as $src => $types) {
      foreach ($types as $type => $dsts) {
        foreach ($dsts as $dst => $edge) {
          $results[$src][] = $dst;
        }
      }
    }

    return $results;
  }

}
