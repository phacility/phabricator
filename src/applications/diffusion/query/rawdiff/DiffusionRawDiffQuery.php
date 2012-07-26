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

abstract class DiffusionRawDiffQuery extends DiffusionQuery {

  private $request;
  private $timeout;
  private $linesOfContext = 65535;
  private $againstCommit;

  final public static function newFromDiffusionRequest(
    DiffusionRequest $request) {
    return parent::newQueryObject(__CLASS__, $request);
  }

  final public function loadRawDiff() {
    return $this->executeQuery();
  }

  final public function setTimeout($timeout) {
    $this->timeout = $timeout;
    return $this;
  }

  final public function getTimeout() {
    return $this->timeout;
  }

  final public function setLinesOfContext($lines_of_context) {
    $this->linesOfContext = $lines_of_context;
    return $this;
  }

  final public function getLinesOfContext() {
    return $this->linesOfContext;
  }

  final public function setAgainstCommit($value) {
    $this->againstCommit = $value;
    return $this;
  }

  final public function getAgainstCommit() {
    return $this->againstCommit;
  }

}
