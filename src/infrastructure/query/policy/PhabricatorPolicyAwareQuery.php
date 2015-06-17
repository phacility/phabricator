<?php

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
 * Normally, you should extend @{class:PhabricatorCursorPagedPolicyAwareQuery},
 * not this class. @{class:PhabricatorCursorPagedPolicyAwareQuery} provides a
 * more practical interface for building usable queries against most object
 * types.
 *
 * NOTE: Although this class extends @{class:PhabricatorOffsetPagedQuery},
 * offset paging with policy filtering is not efficient. All results must be
 * loaded into the application and filtered here: skipping `N` rows via offset
 * is an `O(N)` operation with a large constant. Prefer cursor-based paging
 * with @{class:PhabricatorCursorPagedPolicyAwareQuery}, which can filter far
 * more efficiently in MySQL.
 *
 * @task config     Query Configuration
 * @task exec       Executing Queries
 * @task policyimpl Policy Query Implementation
 */
abstract class PhabricatorPolicyAwareQuery extends PhabricatorOffsetPagedQuery {

  private $viewer;
  private $parentQuery;
  private $rawResultLimit;
  private $capabilities;
  private $workspace = array();
  private $inFlightPHIDs = array();
  private $policyFilteredPHIDs = array();

  /**
   * Should we continue or throw an exception when a query result is filtered
   * by policy rules?
   *
   * Values are `true` (raise exceptions), `false` (do not raise exceptions)
   * and `null` (inherit from parent query, with no exceptions by default).
   */
  private $raisePolicyExceptions;


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


  /**
   * Set the parent query of this query. This is useful for nested queries so
   * that configuration like whether or not to raise policy exceptions is
   * seamlessly passed along to child queries.
   *
   * @return this
   * @task config
   */
  final public function setParentQuery(PhabricatorPolicyAwareQuery $query) {
    $this->parentQuery = $query;
    return $this;
  }


  /**
   * Get the parent query. See @{method:setParentQuery} for discussion.
   *
   * @return PhabricatorPolicyAwareQuery The parent query.
   * @task config
   */
  final public function getParentQuery() {
    return $this->parentQuery;
  }


  /**
   * Hook to configure whether this query should raise policy exceptions.
   *
   * @return this
   * @task config
   */
  final public function setRaisePolicyExceptions($bool) {
    $this->raisePolicyExceptions = $bool;
    return $this;
  }


  /**
   * @return bool
   * @task config
   */
  final public function shouldRaisePolicyExceptions() {
    return (bool)$this->raisePolicyExceptions;
  }


  /**
   * @task config
   */
  final public function requireCapabilities(array $capabilities) {
    $this->capabilities = $capabilities;
    return $this;
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

    $this->setRaisePolicyExceptions(true);
    try {
      $results = $this->execute();
    } catch (Exception $ex) {
      $this->setRaisePolicyExceptions(false);
      throw $ex;
    }

    if (count($results) > 1) {
      throw new Exception(pht('Expected a single result!'));
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
      throw new PhutilInvalidStateException('setViewer');
    }

    $parent_query = $this->getParentQuery();
    if ($parent_query && ($this->raisePolicyExceptions === null)) {
      $this->setRaisePolicyExceptions(
        $parent_query->shouldRaisePolicyExceptions());
    }

    $results = array();

    $filter = $this->getPolicyFilter();

    $offset = (int)$this->getOffset();
    $limit  = (int)$this->getLimit();
    $count  = 0;

    if ($limit) {
      $need = $offset + $limit;
    } else {
      $need = 0;
    }

    $this->willExecute();

    do {
      if ($need) {
        $this->rawResultLimit = min($need - $count, 1024);
      } else {
        $this->rawResultLimit = 0;
      }

      if ($this->canViewerUseQueryApplication()) {
        try {
          $page = $this->loadPage();
        } catch (PhabricatorEmptyQueryException $ex) {
          $page = array();
        }
      } else {
        $page = array();
      }

      if ($page) {
        $maybe_visible = $this->willFilterPage($page);
      } else {
        $maybe_visible = array();
      }

      if ($this->shouldDisablePolicyFiltering()) {
        $visible = $maybe_visible;
      } else {
        $visible = $filter->apply($maybe_visible);

        $policy_filtered = array();
        foreach ($maybe_visible as $key => $object) {
          if (empty($visible[$key])) {
            $phid = $object->getPHID();
            if ($phid) {
              $policy_filtered[$phid] = $phid;
            }
          }
        }
        $this->addPolicyFilteredPHIDs($policy_filtered);
      }

      if ($visible) {
        $this->putObjectsInWorkspace($this->getWorkspaceMapForPage($visible));
        $visible = $this->didFilterPage($visible);
      }

      $removed = array();
      foreach ($maybe_visible as $key => $object) {
        if (empty($visible[$key])) {
          $removed[$key] = $object;
        }
      }

      $this->didFilterResults($removed);

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

      if (!$this->rawResultLimit) {
        // If we don't have a load count, we loaded all the results. We do
        // not need to load another page.
        break;
      }

      if (count($page) < $this->rawResultLimit) {
        // If we have a load count but the unfiltered results contained fewer
        // objects, we know this was the last page of objects; we do not need
        // to load another page because we can deduce it would be empty.
        break;
      }

      $this->nextPage($page);
    } while (true);

    $results = $this->didLoadResults($results);

    return $results;
  }

  private function getPolicyFilter() {
    $filter = new PhabricatorPolicyFilter();
    $filter->setViewer($this->viewer);
    $capabilities = $this->getRequiredCapabilities();
    $filter->requireCapabilities($capabilities);
    $filter->raisePolicyExceptions($this->shouldRaisePolicyExceptions());

    return $filter;
  }

  protected function getRequiredCapabilities() {
    if ($this->capabilities) {
      return $this->capabilities;
    }

    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
    );
  }

  protected function applyPolicyFilter(array $objects, array $capabilities) {
    if ($this->shouldDisablePolicyFiltering()) {
      return $objects;
    }
    $filter = $this->getPolicyFilter();
    $filter->requireCapabilities($capabilities);
    return $filter->apply($objects);
  }

  protected function didRejectResult(PhabricatorPolicyInterface $object) {
    // Some objects (like commits) may be rejected because related objects
    // (like repositories) can not be loaded. In some cases, we may need these
    // related objects to determine the object policy, so it's expected that
    // we may occasionally be unable to determine the policy.

    try {
      $policy = $object->getPolicy(PhabricatorPolicyCapability::CAN_VIEW);
    } catch (Exception $ex) {
      $policy = null;
    }

    // Mark this object as filtered so handles can render "Restricted" instead
    // of "Unknown".
    $phid = $object->getPHID();
    $this->addPolicyFilteredPHIDs(array($phid => $phid));

    $this->getPolicyFilter()->rejectObject(
      $object,
      $policy,
      PhabricatorPolicyCapability::CAN_VIEW);
  }

  public function addPolicyFilteredPHIDs(array $phids) {
    $this->policyFilteredPHIDs += $phids;
    if ($this->getParentQuery()) {
      $this->getParentQuery()->addPolicyFilteredPHIDs($phids);
    }
    return $this;
  }

  /**
   * Return a map of all object PHIDs which were loaded in the query but
   * filtered out by policy constraints. This allows a caller to distinguish
   * between objects which do not exist (or, at least, were filtered at the
   * content level) and objects which exist but aren't visible.
   *
   * @return map<phid, phid> Map of object PHIDs which were filtered
   *   by policies.
   * @task exec
   */
  public function getPolicyFilteredPHIDs() {
    return $this->policyFilteredPHIDs;
  }


/* -(  Query Workspace  )---------------------------------------------------- */


  /**
   * Put a map of objects into the query workspace. Many queries perform
   * subqueries, which can eventually end up loading the same objects more than
   * once (often to perform policy checks).
   *
   * For example, loading a user may load the user's profile image, which might
   * load the user object again in order to verify that the viewer has
   * permission to see the file.
   *
   * The "query workspace" allows queries to load objects from elsewhere in a
   * query block instead of refetching them.
   *
   * When using the query workspace, it's important to obey two rules:
   *
   * **Never put objects into the workspace which the viewer may not be able
   * to see**. You need to apply all policy filtering //before// putting
   * objects in the workspace. Otherwise, subqueries may read the objects and
   * use them to permit access to content the user shouldn't be able to view.
   *
   * **Fully enrich objects pulled from the workspace.** After pulling objects
   * from the workspace, you still need to load and attach any additional
   * content the query requests. Otherwise, a query might return objects without
   * requested content.
   *
   * Generally, you do not need to update the workspace yourself: it is
   * automatically populated as a side effect of objects surviving policy
   * filtering.
   *
   * @param map<phid, PhabricatorPolicyInterface> Objects to add to the query
   *   workspace.
   * @return this
   * @task workspace
   */
  public function putObjectsInWorkspace(array $objects) {
    assert_instances_of($objects, 'PhabricatorPolicyInterface');

    $viewer_phid = $this->getViewer()->getPHID();

    // The workspace is scoped per viewer to prevent accidental contamination.
    if (empty($this->workspace[$viewer_phid])) {
      $this->workspace[$viewer_phid] = array();
    }

    $this->workspace[$viewer_phid] += $objects;

    return $this;
  }


  /**
   * Retrieve objects from the query workspace. For more discussion about the
   * workspace mechanism, see @{method:putObjectsInWorkspace}. This method
   * searches both the current query's workspace and the workspaces of parent
   * queries.
   *
   * @param list<phid> List of PHIDs to retrieve.
   * @return this
   * @task workspace
   */
  public function getObjectsFromWorkspace(array $phids) {
    $viewer_phid = $this->getViewer()->getPHID();

    $results = array();
    foreach ($phids as $key => $phid) {
      if (isset($this->workspace[$viewer_phid][$phid])) {
        $results[$phid] = $this->workspace[$viewer_phid][$phid];
        unset($phids[$key]);
      }
    }

    if ($phids && $this->getParentQuery()) {
      $results += $this->getParentQuery()->getObjectsFromWorkspace($phids);
    }

    return $results;
  }


  /**
   * Convert a result page to a `<phid, PhabricatorPolicyInterface>` map.
   *
   * @param list<PhabricatorPolicyInterface> Objects.
   * @return map<phid, PhabricatorPolicyInterface> Map of objects which can
   *   be put into the workspace.
   * @task workspace
   */
  protected function getWorkspaceMapForPage(array $results) {
    $map = array();
    foreach ($results as $result) {
      $phid = $result->getPHID();
      if ($phid !== null) {
        $map[$phid] = $result;
      }
    }

    return $map;
  }


  /**
   * Mark PHIDs as in flight.
   *
   * PHIDs which are "in flight" are actively being queried for. Using this
   * list can prevent infinite query loops by aborting queries which cycle.
   *
   * @param list<phid> List of PHIDs which are now in flight.
   * @return this
   */
  public function putPHIDsInFlight(array $phids) {
    foreach ($phids as $phid) {
      $this->inFlightPHIDs[$phid] = $phid;
    }
    return $this;
  }


  /**
   * Get PHIDs which are currently in flight.
   *
   * PHIDs which are "in flight" are actively being queried for.
   *
   * @return map<phid, phid> PHIDs currently in flight.
   */
  public function getPHIDsInFlight() {
    $results = $this->inFlightPHIDs;
    if ($this->getParentQuery()) {
      $results += $this->getParentQuery()->getPHIDsInFlight();
    }
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


  /**
   * Hook for applying a page filter prior to the privacy filter. This allows
   * you to drop some items from the result set without creating problems with
   * pagination or cursor updates. You can also load and attach data which is
   * required to perform policy filtering.
   *
   * Generally, you should load non-policy data and perform non-policy filtering
   * later, in @{method:didFilterPage}. Strictly fewer objects will make it that
   * far (so the program will load less data) and subqueries from that context
   * can use the query workspace to further reduce query load.
   *
   * This method will only be called if data is available. Implementations
   * do not need to handle the case of no results specially.
   *
   * @param   list<wild>  Results from `loadPage()`.
   * @return  list<PhabricatorPolicyInterface> Objects for policy filtering.
   * @task policyimpl
   */
  protected function willFilterPage(array $page) {
    return $page;
  }

  /**
   * Hook for performing additional non-policy loading or filtering after an
   * object has satisfied all policy checks. Generally, this means loading and
   * attaching related data.
   *
   * Subqueries executed during this phase can use the query workspace, which
   * may improve performance or make circular policies resolvable. Data which
   * is not necessary for policy filtering should generally be loaded here.
   *
   * This callback can still filter objects (for example, if attachable data
   * is discovered to not exist), but should not do so for policy reasons.
   *
   * This method will only be called if data is available. Implementations do
   * not need to handle the case of no results specially.
   *
   * @param list<wild> Results from @{method:willFilterPage()}.
   * @return list<PhabricatorPolicyInterface> Objects after additional
   *   non-policy processing.
   */
  protected function didFilterPage(array $page) {
    return $page;
  }


  /**
   * Hook for removing filtered results from alternate result sets. This
   * hook will be called with any objects which were returned by the query but
   * filtered for policy reasons. The query should remove them from any cached
   * or partial result sets.
   *
   * @param list<wild>  List of objects that should not be returned by alternate
   *                    result mechanisms.
   * @return void
   * @task policyimpl
   */
  protected function didFilterResults(array $results) {
    return;
  }


  /**
   * Hook for applying final adjustments before results are returned. This is
   * used by @{class:PhabricatorCursorPagedPolicyAwareQuery} to reverse results
   * that are queried during reverse paging.
   *
   * @param   list<PhabricatorPolicyInterface> Query results.
   * @return  list<PhabricatorPolicyInterface> Final results.
   * @task policyimpl
   */
  protected function didLoadResults(array $results) {
    return $results;
  }


  /**
   * Allows a subclass to disable policy filtering. This method is dangerous.
   * It should be used only if the query loads data which has already been
   * filtered (for example, because it wraps some other query which uses
   * normal policy filtering).
   *
   * @return bool True to disable all policy filtering.
   * @task policyimpl
   */
  protected function shouldDisablePolicyFiltering() {
    return false;
  }


  /**
   * If this query belongs to an application, return the application class name
   * here. This will prevent the query from returning results if the viewer can
   * not access the application.
   *
   * If this query does not belong to an application, return `null`.
   *
   * @return string|null Application class name.
   */
  abstract public function getQueryApplicationClass();


  /**
   * Determine if the viewer has permission to use this query's application.
   * For queries which aren't part of an application, this method always returns
   * true.
   *
   * @return bool True if the viewer has application-level permission to
   *   execute the query.
   */
  public function canViewerUseQueryApplication() {
    $class = $this->getQueryApplicationClass();
    if (!$class) {
      return true;
    }

    $viewer = $this->getViewer();
    return PhabricatorApplication::isClassInstalledForViewer($class, $viewer);
  }

}
