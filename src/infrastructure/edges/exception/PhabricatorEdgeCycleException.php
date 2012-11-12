<?php

final class PhabricatorEdgeCycleException extends Exception {

  private $cycleEdgeType;
  private $cycle;

  public function __construct($cycle_edge_type, array $cycle) {
    $this->cycleEdgeType = $cycle_edge_type;
    $this->cycle = $cycle;

    $cycle_list = implode(', ', $cycle);

    parent::__construct(
      "Graph cycle detected (type={$cycle_edge_type}, cycle={$cycle_list}).");
  }

  public function getCycle() {
    return $this->cycle;
  }

  public function getCycleEdgeType() {
    return $this->cycleEdgeType;
  }

}
