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

abstract class PhabricatorPolicyQuery extends PhabricatorQuery {

  private $limit;
  private $viewer;
  private $raisePolicyExceptions;

  final public function setViewer($viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  final public function getViewer() {
    return $this->viewer;
  }

  final public function setLimit($limit) {
    $this->limit = $limit;
    return $this;
  }

  final public function getLimit() {
    return $this->limit;
  }

  final public function executeOne() {

    $this->raisePolicyExceptions = true;
    try {
      $results = $this->execute();
    } catch (Exception $ex) {
      $this->raisePolicyExceptions = false;
      throw $ex;
    }

    if (count($results) > 1) {
      throw new Exception("Expected a single result!");
    }
    return head($results);
  }

  final public function execute() {
    if (!$this->viewer) {
      throw new Exception("Call setViewer() before execute()!");
    }

    $results = array();

    $filter = new PhabricatorPolicyFilter();
    $filter->setViewer($this->viewer);
    $filter->setCapability(PhabricatorPolicyCapability::CAN_VIEW);

    $filter->raisePolicyExceptions($this->raisePolicyExceptions);

    do {
      $page = $this->loadPage();

      $visible = $filter->apply($page);
      foreach ($visible as $key => $result) {
        $results[$key] = $result;
        if ($this->getLimit() && count($results) >= $this->getLimit()) {
          break 2;
        }
      }

      if (!$this->getLimit() || (count($page) < $this->getLimit())) {
        break;
      }

      $this->nextPage($page);
    } while (true);

    return $results;
  }

  abstract protected function loadPage();
  abstract protected function nextPage(array $page);

}
