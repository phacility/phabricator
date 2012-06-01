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

abstract class DiffusionTagListQuery extends DiffusionQuery {

  private $limit;
  private $offset;

  public function setOffset($offset) {
    $this->offset = $offset;
    return $this;
  }

  public function getOffset() {
    return $this->offset;
  }

  public function setLimit($limit) {
    $this->limit = $limit;
    return $this;
  }

  protected function getLimit() {
    return $this->limit;
  }

  final public static function newFromDiffusionRequest(
    DiffusionRequest $request) {
    return self::newQueryObject(__CLASS__, $request);
  }

  final public function loadTags() {
    return $this->executeQuery();
  }

}
