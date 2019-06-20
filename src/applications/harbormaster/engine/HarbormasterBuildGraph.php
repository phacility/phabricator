<?php

/**
 * Directed graph representing a build plan
 */
final class HarbormasterBuildGraph extends AbstractDirectedGraph {

  private $stepMap;

  public static function determineDependencyExecution(
    HarbormasterBuildPlan $plan) {

    $steps = id(new HarbormasterBuildStepQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withBuildPlanPHIDs(array($plan->getPHID()))
      ->execute();

    $steps_by_phid = mpull($steps, null, 'getPHID');
    $step_phids = mpull($steps, 'getPHID');

    if (count($steps) === 0) {
      return array();
    }

    $graph = id(new HarbormasterBuildGraph($steps_by_phid))
      ->addNodes($step_phids);

    $raw_results = $graph->getNodesInRoughTopologicalOrder();

    $results = array();
    foreach ($raw_results as $node) {
      $results[] = array(
        'node' => $steps_by_phid[$node['node']],
        'depth' => $node['depth'],
        'cycle' => $node['cycle'],
      );
    }

    return $results;
  }

  public function __construct($step_map) {
    $this->stepMap = $step_map;
  }

  protected function loadEdges(array $nodes) {
    $map = array();
    foreach ($nodes as $node) {
      $step = $this->stepMap[$node];

      try {
        $deps = $step->getStepImplementation()->getDependencies($step);
      } catch (Exception $ex) {
        $deps = array();
      }

      $map[$node] = array();
      foreach ($deps as $dep) {
        $map[$node][] = $dep;
      }
    }

    return $map;
  }

}
