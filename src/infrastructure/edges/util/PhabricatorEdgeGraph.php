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

final class PhabricatorEdgeGraph extends AbstractDirectedGraph {

  private $edgeType;

  public function setEdgeType($edge_type) {
    $this->edgeType = $edge_type;
    return $this;
  }

  protected function loadEdges(array $nodes) {
    if (!$this->edgeType) {
      throw new Exception("Set edge type before loading graph!");
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
