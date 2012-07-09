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
