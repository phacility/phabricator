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
 * A @{class:PhabricatorQuery} which filters results according to visibility
 * policies for the querying user. Broadly, this class allows you to implement
 * a query that returns only objects the user is allowed to see.
 *
 *   $results = id(new ExampleQuery())
 *     ->setViewer($user)
 *     ->withConstraint($example)
 *     ->execute();
 *
 * Normally, you should extend @{class:PhabricatorCursorPagedPolicyQuery}, not
 * this class. @{class:PhabricatorCursorPagedPolicyQuery} provides a more
 * practical interface for building usable queries against most object types.
 *
 * NOTE: Although this class extends @{class:PhabricatorOffsetPagedQuery},
 * offset paging with policy filtering is not efficient. All results must be
 * loaded into the application and filtered here: skipping `N` rows via offset
 * is an `O(N)` operation with a large constant. Prefer cursor-based paging
 * with @{class:PhabricatorCursorPagedPolicyQuery}, which can filter far more
 * efficiently in MySQL.
 *
 * @task config     Query Configuration
 * @task exec       Executing Queries
 * @task policyimpl Policy Query Implementation
 */
abstract class PhabricatorPolicyQuery extends PhabricatorOffsetPagedQuery {

  private $viewer;
  private $raisePolicyExceptions;
  private $rawResultLimit;


/* -(  Query Configuration  )------------------------------------------------ */


  /**
   * Set the viewer who is executing the query. Results will be filtered
   * according to the viewer's capabilities. You must set a viewer to execute
   * a policy query.
   *
   * @param PhabricatorUser The viewing user.
   * @return this
   * @task config
   */
  final public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }


  /**
   * Get the query's viewer.
   *
   * @return PhabricatorUser The viewing user.
   * @task config
   */
  final public function getViewer() {
    return $this->viewer;
  }


/* -(  Query Execution  )---------------------------------------------------- */


  /**
   * Execute the query, expecting a single result. This method simplifies
   * loading objects for detail pages or edit views.
   *
   *   // Load one result by ID.
   *   $obj = id(new ExampleQuery())
   *     ->setViewer($user)
   *     ->withIDs(array($id))
   *     ->executeOne();
   *   if (!$obj) {
   *     return new Aphront404Response();
   *   }
   *
   * If zero results match the query, this method returns `null`.
   *
   * If one result matches the query, this method returns that result.
   *
   * If two or more results match the query, this method throws an exception.
   * You should use this method only when the query constraints guarantee at
   * most one match (e.g., selecting a specific ID or PHID).
   *
   * If one result matches the query but it is caught by the policy filter (for
   * example, the user is trying to view or edit an object which exists but
   * which they do not have permission to see) a policy exception is thrown.
   *
   * @return mixed Single result, or null.
   * @task exec
   */
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

    if (!$results) {
      return null;
    }

    return head($results);
  }


  /**
   * Execute the query, loading all visible results.
   *
   * @return list<PhabricatorPolicyInterface> Result objects.
   * @task exec
   */
  final public function execute() {
    if (!$this->viewer) {
      throw new Exception("Call setViewer() before execute()!");
    }

    $results = array();

    $filter = new PhabricatorPolicyFilter();
    $filter->setViewer($this->viewer);
    $filter->setCapability(PhabricatorPolicyCapability::CAN_VIEW);

    $filter->raisePolicyExceptions($this->raisePolicyExceptions);

    $offset = (int)$this->getOffset();
    $limit  = (int)$this->getLimit();
    $count  = 0;

    $need = null;
    if ($offset) {
      $need = $offset + $limit;
    }

    $this->willExecute();

    do {

      // Figure out how many results to load. "0" means "all results".
      $load = 0;
      if ($need && ($count < $offset)) {
        // This cap is just an arbitrary limit to keep memory usage from going
        // crazy for large offsets; we can't execute them efficiently, but
        // it should be possible to execute them without crashing.
        $load = min($need, 1024);
      } else if ($limit) {
        // Otherwise, just load the number of rows we're after. Note that it
        // might be more efficient to load more rows than this (if we expect
        // about 5% of objects to be filtered, loading 105% of the limit might
        // be better) or fewer rows than this (if we already have 95 rows and
        // only need 100, loading only 5 rows might be better), but we currently
        // just use the simplest heuristic since we don't have enough data
        // about policy queries in the real world to tweak it.
        $load = $limit;
      }
      $this->rawResultLimit = $load;


      $page = $this->loadPage();

      $visible = $filter->apply($page);
      foreach ($visible as $key => $result) {
        ++$count;

        // If we have an offset, we just ignore that many results and start
        // storing them only once we've hit the offset. This reduces memory
        // requirements for large offsets, compared to storing them all and
        // slicing them away later.
        if ($count > $offset) {
          $results[$key] = $result;
        }

        if ($need && ($count >= $need)) {
          // If we have all the rows we need, break out of the paging query.
          break 2;
        }
      }

      if (!$load) {
        // If we don't have a load count, we loaded all the results. We do
        // not need to load another page.
        break;
      }

      if (count($page) < $load) {
        // If we have a load count but the unfiltered results contained fewer
        // objects, we know this was the last page of objects; we do not need
        // to load another page because we can deduce it would be empty.
        break;
      }

      $this->nextPage($page);
    } while (true);

    return $results;
  }


/* -(  Policy Query Implementation  )---------------------------------------- */


  /**
   * Get the number of results @{method:loadPage} should load. If the value is
   * 0, @{method:loadPage} should load all available results.
   *
   * @return int The number of results to load, or 0 for all results.
   * @task policyimpl
   */
  final protected function getRawResultLimit() {
    return $this->rawResultLimit;
  }


  /**
   * Hook invoked before query execution. Generally, implementations should
   * reset any internal cursors.
   *
   * @return void
   * @task policyimpl
   */
  protected function willExecute() {
    return;
  }


  /**
   * Load a raw page of results. Generally, implementations should load objects
   * from the database. They should attempt to return the number of results
   * hinted by @{method:getRawResultLimit}.
   *
   * @return list<PhabricatorPolicyInterface> List of filterable policy objects.
   * @task policyimpl
   */
  abstract protected function loadPage();


  /**
   * Update internal state so that the next call to @{method:loadPage} will
   * return new results. Generally, you should adjust a cursor position based
   * on the provided result page.
   *
   * @param list<PhabricatorPolicyInterface> The current page of results.
   * @return void
   * @task policyimpl
   */
  abstract protected function nextPage(array $page);

}
