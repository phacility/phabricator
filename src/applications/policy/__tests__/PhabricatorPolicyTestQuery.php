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
final class PhabricatorPolicyTestQuery
  extends PhabricatorPolicyQuery {

  private $results;

  public function setResults(array $results) {
    $this->results = $results;
    return $this;
  }

  public function loadPage() {
    return $this->results;
  }

  public function nextPage(array $page) {
    return null;
  }

}
