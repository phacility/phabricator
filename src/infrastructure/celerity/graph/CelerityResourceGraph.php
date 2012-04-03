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
