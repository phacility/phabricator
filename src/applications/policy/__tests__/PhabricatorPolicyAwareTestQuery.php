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

/**
 * Configurable test query for implementing Policy unit tests.
 */
final class PhabricatorPolicyAwareTestQuery
  extends PhabricatorPolicyAwareQuery {

  private $results;
  private $offset = 0;

  public function setResults(array $results) {
    $this->results = $results;
    return $this;
  }

  protected function willExecute() {
    $this->offset = 0;
  }

  public function loadPage() {
    if ($this->getRawResultLimit()) {
      return array_slice(
        $this->results,
        $this->offset,
        $this->getRawResultLimit());
    } else {
      return array_slice($this->results, $this->offset);
    }
  }

  public function nextPage(array $page) {
    $this->offset += count($page);
  }

}
