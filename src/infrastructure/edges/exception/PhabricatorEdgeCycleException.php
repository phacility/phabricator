<?php

final class PhabricatorEdgeCycleException extends Exception {

  private $cycleEdgeType;
  private $cycle;

  public function __construct($cycle_edge_type, array $cycle) {
    $this->cycleEdgeType = $cycle_edge_type;
    $this->cycle = $cycle;

    $cycle_list = implode(', ', $cycle);

    parent::__construct(
      pht(
        'Graph cycle detected (type=%s, cycle=%s).',
        $cycle_edge_type,
        $cycle_list));
  }

  public function getCycle() {
    return $this->cycle;
  }

  public function getCycleEdgeType() {
    return $this->cycleEdgeType;
  }

}
