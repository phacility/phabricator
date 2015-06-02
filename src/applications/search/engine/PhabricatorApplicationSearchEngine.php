<?php

/**
 * Represents an abstract search engine for an application. It supports
 * creating and storing saved queries.
 *
 * @task construct  Constructing Engines
 * @task app        Applications
 * @task builtin    Builtin Queries
 * @task uri        Query URIs
 * @task dates      Date Filters
 * @task order      Result Ordering
 * @task read       Reading Utilities
 * @task exec       Paging and Executing Queries
 * @task render     Rendering Results
 */
abstract class PhabricatorApplicationSearchEngine {

  private $application;
  private $viewer;
  private $errors = array();
  private $customFields = false;
  private $request;
  private $context;

  const CONTEXT_LIST  = 'list';
  const CONTEXT_PANEL = 'panel';

  public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  protected function requireViewer() {
    if (!$this->viewer) {
      throw new PhutilInvalidStateException('setViewer');
    }
    return $this->viewer;
  }

  public function setContext($context) {
    $this->context = $context;
    return $this;
  }

  public function isPanelContext() {
    return ($this->context == self::CONTEXT_PANEL);
  }

  public function canUseInPanelContext() {
    return true;
  }

  public function saveQuery(PhabricatorSavedQuery $query) {
    $query->setEngineClassName(get_class($this));

    $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
    try {
      $query->save();
    } catch (AphrontDuplicateKeyQueryException $ex) {
      // Ignore, this is just a repeated search.
    }
    unset($unguarded);
  }

  /**
   * Create a saved query object from the request.
   *
   * @param AphrontRequest The search request.
   * @return PhabricatorSavedQuery
   */
  abstract public function buildSavedQueryFromRequest(
    AphrontRequest $request);

  /**
   * Executes the saved query.
   *
   * @param PhabricatorSavedQuery The saved query to operate on.
   * @return The result of the query.
   */
  abstract public function buildQueryFromSavedQuery(
    PhabricatorSavedQuery $saved);

  /**
   * Builds the search form using the request.
   *
   * @param AphrontFormView       Form to populate.
   * @param PhabricatorSavedQuery The query from which to build the form.
   * @return void
   */
  abstract public function buildSearchForm(
    AphrontFormView $form,
    PhabricatorSavedQuery $query);

  public function getErrors() {
    return $this->errors;
  }

  public function addError($error) {
    $this->errors[] = $error;
    return $this;
  }

  /**
   * Return an application URI corresponding to the results page of a query.
   * Normally, this is something like `/application/query/QUERYKEY/`.
   *
   * @param   string  The query key to build a URI for.
   * @return  string  URI where the query can be executed.
   * @task uri
   */
  public function getQueryResultsPageURI($query_key) {
    return $this->getURI('query/'.$query_key.'/');
  }


  /**
   * Return an application URI for query management. This is used when, e.g.,
   * a query deletion operation is cancelled.
   *
   * @return  string  URI where queries can be managed.
   * @task uri
   */
  public function getQueryManagementURI() {
    return $this->getURI('query/edit/');
  }


  /**
   * Return the URI to a path within the application. Used to construct default
   * URIs for management and results.
   *
   * @return string URI to path.
   * @task uri
   */
  abstract protected function getURI($path);


  /**
   * Return a human readable description of the type of objects this query
   * searches for.
   *
   * For example, "Tasks" or "Commits".
   *
   * @return string Human-readable description of what this engine is used to
   *   find.
   */
  abstract public function getResultTypeDescription();


  public function newSavedQuery() {
    return id(new PhabricatorSavedQuery())
      ->setEngineClassName(get_class($this));
  }

  public function addNavigationItems(PHUIListView $menu) {
    $viewer = $this->requireViewer();

    $menu->newLabel(pht('Queries'));

    $named_queries = $this->loadEnabledNamedQueries();

    foreach ($named_queries as $query) {
      $key = $query->getQueryKey();
      $uri = $this->getQueryResultsPageURI($key);
      $menu->newLink($query->getQueryName(), $uri, 'query/'.$key);
    }

    if ($viewer->isLoggedIn()) {
      $manage_uri = $this->getQueryManagementURI();
      $menu->newLink(pht('Edit Queries...'), $manage_uri, 'query/edit');
    }

    $menu->newLabel(pht('Search'));
    $advanced_uri = $this->getQueryResultsPageURI('advanced');
    $menu->newLink(pht('Advanced Search'), $advanced_uri, 'query/advanced');

    return $this;
  }

  public function loadAllNamedQueries() {
    $viewer = $this->requireViewer();

    $named_queries = id(new PhabricatorNamedQueryQuery())
      ->setViewer($viewer)
      ->withUserPHIDs(array($viewer->getPHID()))
      ->withEngineClassNames(array(get_class($this)))
      ->execute();
    $named_queries = mpull($named_queries, null, 'getQueryKey');

    $builtin = $this->getBuiltinQueries($viewer);
    $builtin = mpull($builtin, null, 'getQueryKey');

    foreach ($named_queries as $key => $named_query) {
      if ($named_query->getIsBuiltin()) {
        if (isset($builtin[$key])) {
          $named_queries[$key]->setQueryName($builtin[$key]->getQueryName());
          unset($builtin[$key]);
        } else {
          unset($named_queries[$key]);
        }
      }

      unset($builtin[$key]);
    }

    $named_queries = msort($named_queries, 'getSortKey');

    return $named_queries + $builtin;
  }

  public function loadEnabledNamedQueries() {
    $named_queries = $this->loadAllNamedQueries();
    foreach ($named_queries as $key => $named_query) {
      if ($named_query->getIsBuiltin() && $named_query->getIsDisabled()) {
        unset($named_queries[$key]);
      }
    }
    return $named_queries;
  }

  protected function setQueryProjects(
    PhabricatorCursorPagedPolicyAwareQuery $query,
    PhabricatorSavedQuery $saved) {

    $datasource = id(new PhabricatorProjectLogicalDatasource())
      ->setViewer($this->requireViewer());

    $projects = $saved->getParameter('projects', array());
    $constraints = $datasource->evaluateTokens($projects);

    if ($constraints) {
      $query->withEdgeLogicConstraints(
        PhabricatorProjectObjectHasProjectEdgeType::EDGECONST,
        $constraints);
    }
  }


/* -(  Applications  )------------------------------------------------------- */


  protected function getApplicationURI($path = '') {
    return $this->getApplication()->getApplicationURI($path);
  }

  protected function getApplication() {
    if (!$this->application) {
      $class = $this->getApplicationClassName();

      $this->application = id(new PhabricatorApplicationQuery())
        ->setViewer($this->requireViewer())
        ->withClasses(array($class))
        ->withInstalled(true)
        ->executeOne();

      if (!$this->application) {
        throw new Exception(
          pht(
            'Application "%s" is not installed!',
            $class));
      }
    }

    return $this->application;
  }

  abstract public function getApplicationClassName();


/* -(  Constructing Engines  )----------------------------------------------- */


  /**
   * Load all available application search engines.
   *
   * @return list<PhabricatorApplicationSearchEngine> All available engines.
   * @task construct
   */
  public static function getAllEngines() {
    $engines = id(new PhutilSymbolLoader())
      ->setAncestorClass(__CLASS__)
      ->loadObjects();

    return $engines;
  }


  /**
   * Get an engine by class name, if it exists.
   *
   * @return PhabricatorApplicationSearchEngine|null Engine, or null if it does
   *   not exist.
   * @task construct
   */
  public static function getEngineByClassName($class_name) {
    return idx(self::getAllEngines(), $class_name);
  }


/* -(  Builtin Queries  )---------------------------------------------------- */


  /**
   * @task builtin
   */
  public function getBuiltinQueries() {
    $names = $this->getBuiltinQueryNames();

    $queries = array();
    $sequence = 0;
    foreach ($names as $key => $name) {
      $queries[$key] = id(new PhabricatorNamedQuery())
        ->setUserPHID($this->requireViewer()->getPHID())
        ->setEngineClassName(get_class($this))
        ->setQueryName($name)
        ->setQueryKey($key)
        ->setSequence((1 << 24) + $sequence++)
        ->setIsBuiltin(true);
    }

    return $queries;
  }


  /**
   * @task builtin
   */
  public function getBuiltinQuery($query_key) {
    if (!$this->isBuiltinQuery($query_key)) {
      throw new Exception(pht("'%s' is not a builtin!", $query_key));
    }
    return idx($this->getBuiltinQueries(), $query_key);
  }


  /**
   * @task builtin
   */
  protected function getBuiltinQueryNames() {
    return array();
  }


  /**
   * @task builtin
   */
  public function isBuiltinQuery($query_key) {
    $builtins = $this->getBuiltinQueries();
    return isset($builtins[$query_key]);
  }


  /**
   * @task builtin
   */
  public function buildSavedQueryFromBuiltin($query_key) {
    throw new Exception(pht("Builtin '%s' is not supported!", $query_key));
  }


/* -(  Reading Utilities )--------------------------------------------------- */


  /**
   * Read a list of user PHIDs from a request in a flexible way. This method
   * supports either of these forms:
   *
   *   users[]=alincoln&users[]=htaft
   *   users=alincoln,htaft
   *
   * Additionally, users can be specified either by PHID or by name.
   *
   * The main goal of this flexibility is to allow external programs to generate
   * links to pages (like "alincoln's open revisions") without needing to make
   * API calls.
   *
   * @param AphrontRequest  Request to read user PHIDs from.
   * @param string          Key to read in the request.
   * @param list<const>     Other permitted PHID types.
   * @return list<phid>     List of user PHIDs and selector functions.
   * @task read
   */
  protected function readUsersFromRequest(
    AphrontRequest $request,
    $key,
    array $allow_types = array()) {

    $list = $this->readListFromRequest($request, $key);

    $phids = array();
    $names = array();
    $allow_types = array_fuse($allow_types);
    $user_type = PhabricatorPeopleUserPHIDType::TYPECONST;
    foreach ($list as $item) {
      $type = phid_get_type($item);
      if ($type == $user_type) {
        $phids[] = $item;
      } else if (isset($allow_types[$type])) {
        $phids[] = $item;
      } else {
        if (PhabricatorTypeaheadDatasource::isFunctionToken($item)) {
          // If this is a function, pass it through unchanged; we'll evaluate
          // it later.
          $phids[] = $item;
        } else {
          $names[] = $item;
        }
      }
    }

    if ($names) {
      $users = id(new PhabricatorPeopleQuery())
        ->setViewer($this->requireViewer())
        ->withUsernames($names)
        ->execute();
      foreach ($users as $user) {
        $phids[] = $user->getPHID();
      }
      $phids = array_unique($phids);
    }

    return $phids;
  }


  /**
   * Read a list of project PHIDs from a request in a flexible way.
   *
   * @param AphrontRequest  Request to read user PHIDs from.
   * @param string          Key to read in the request.
   * @return list<phid>     List of projet PHIDs and selector functions.
   * @task read
   */
  protected function readProjectsFromRequest(AphrontRequest $request, $key) {
    $list = $this->readListFromRequest($request, $key);

    $phids = array();
    $slugs = array();
    $project_type = PhabricatorProjectProjectPHIDType::TYPECONST;
    foreach ($list as $item) {
      $type = phid_get_type($item);
      if ($type == $project_type) {
        $phids[] = $item;
      } else {
        if (PhabricatorTypeaheadDatasource::isFunctionToken($item)) {
          // If this is a function, pass it through unchanged; we'll evaluate
          // it later.
          $phids[] = $item;
        } else {
          $slugs[] = $item;
        }
      }
    }

    if ($slugs) {
      $projects = id(new PhabricatorProjectQuery())
        ->setViewer($this->requireViewer())
        ->withSlugs($slugs)
        ->execute();
      foreach ($projects as $project) {
        $phids[] = $project->getPHID();
      }
      $phids = array_unique($phids);
    }

    return $phids;
  }


  /**
   * Read a list of subscribers from a request in a flexible way.
   *
   * @param AphrontRequest  Request to read PHIDs from.
   * @param string          Key to read in the request.
   * @return list<phid>     List of object PHIDs.
   * @task read
   */
  protected function readSubscribersFromRequest(
    AphrontRequest $request,
    $key) {
    return $this->readUsersFromRequest(
      $request,
      $key,
      array(
        PhabricatorProjectProjectPHIDType::TYPECONST,
      ));
  }


  /**
   * Read a list of generic PHIDs from a request in a flexible way. Like
   * @{method:readUsersFromRequest}, this method supports either array or
   * comma-delimited forms. Objects can be specified either by PHID or by
   * object name.
   *
   * @param AphrontRequest  Request to read PHIDs from.
   * @param string          Key to read in the request.
   * @param list<const>     Optional, list of permitted PHID types.
   * @return list<phid>     List of object PHIDs.
   *
   * @task read
   */
  protected function readPHIDsFromRequest(
    AphrontRequest $request,
    $key,
    array $allow_types = array()) {

    $list = $this->readListFromRequest($request, $key);

    $objects = id(new PhabricatorObjectQuery())
      ->setViewer($this->requireViewer())
      ->withNames($list)
      ->execute();
    $list = mpull($objects, 'getPHID');

    if (!$list) {
      return array();
    }

    // If only certain PHID types are allowed, filter out all the others.
    if ($allow_types) {
      $allow_types = array_fuse($allow_types);
      foreach ($list as $key => $phid) {
        if (empty($allow_types[phid_get_type($phid)])) {
          unset($list[$key]);
        }
      }
    }

    return $list;
  }


  /**
   * Read a list of items from the request, in either array format or string
   * format:
   *
   *   list[]=item1&list[]=item2
   *   list=item1,item2
   *
   * This provides flexibility when constructing URIs, especially from external
   * sources.
   *
   * @param AphrontRequest  Request to read strings from.
   * @param string          Key to read in the request.
   * @return list<string>   List of values.
   */
  protected function readListFromRequest(
    AphrontRequest $request,
    $key) {
    $list = $request->getArr($key, null);
    if ($list === null) {
      $list = $request->getStrList($key);
    }

    if (!$list) {
      return array();
    }

    return $list;
  }

  protected function readDateFromRequest(
    AphrontRequest $request,
    $key) {

    $value = AphrontFormDateControlValue::newFromRequest($request, $key);

    if ($value->isEmpty()) {
      return null;
    }

    return $value->getDictionary();
  }

  protected function readBoolFromRequest(
    AphrontRequest $request,
    $key) {
    if (!strlen($request->getStr($key))) {
      return null;
    }
    return $request->getBool($key);
  }


  protected function getBoolFromQuery(PhabricatorSavedQuery $query, $key) {
    $value = $query->getParameter($key);
    if ($value === null) {
      return $value;
    }
    return $value ? 'true' : 'false';
  }


/* -(  Dates  )-------------------------------------------------------------- */


  /**
   * @task dates
   */
  protected function parseDateTime($date_time) {
    if (!strlen($date_time)) {
      return null;
    }

    return PhabricatorTime::parseLocalTime($date_time, $this->requireViewer());
  }


  /**
   * @task dates
   */
  protected function buildDateRange(
    AphrontFormView $form,
    PhabricatorSavedQuery $saved_query,
    $start_key,
    $start_name,
    $end_key,
    $end_name) {

    $start_str = $saved_query->getParameter($start_key);
    $start = null;
    if (strlen($start_str)) {
      $start = $this->parseDateTime($start_str);
      if (!$start) {
        $this->addError(
          pht(
            '"%s" date can not be parsed.',
            $start_name));
      }
    }


    $end_str = $saved_query->getParameter($end_key);
    $end = null;
    if (strlen($end_str)) {
      $end = $this->parseDateTime($end_str);
      if (!$end) {
        $this->addError(
          pht(
            '"%s" date can not be parsed.',
            $end_name));
      }
    }

    if ($start && $end && ($start >= $end)) {
      $this->addError(
        pht(
          '"%s" must be a date before "%s".',
          $start_name,
          $end_name));
    }

    $form
      ->appendChild(
        id(new PHUIFormFreeformDateControl())
          ->setName($start_key)
          ->setLabel($start_name)
          ->setValue($start_str))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setName($end_key)
          ->setLabel($end_name)
          ->setValue($end_str));
  }


/* -(  Result Ordering  )---------------------------------------------------- */


  /**
   * Save order selection to a @{class:PhabricatorSavedQuery}.
   */
  protected function saveQueryOrder(
    PhabricatorSavedQuery $saved,
    AphrontRequest $request) {

    $saved->setParameter('order', $request->getStr('order'));

    return $this;
  }


  /**
   * Set query ordering from a saved value.
   */
  protected function setQueryOrder(
    PhabricatorCursorPagedPolicyAwareQuery $query,
    PhabricatorSavedQuery $saved) {

    $order = $saved->getParameter('order');
    $builtin = $query->getBuiltinOrders();
    if (strlen($order) && isset($builtin[$order])) {
      $query->setOrder($order);
    } else {
      // If the order is invalid or not available, we choose the first
      // builtin order. This isn't always the default order for the query,
      // but is the first value in the "Order" dropdown, and makes the query
      // behavior more consistent with the UI. In queries where the two
      // orders differ, this order is the preferred order for humans.
      $query->setOrder(head_key($builtin));
    }

    return $this;
  }



  protected function appendOrderFieldsToForm(
    AphrontFormView $form,
    PhabricatorSavedQuery $saved,
    PhabricatorCursorPagedPolicyAwareQuery $query) {

    $orders = $query->getBuiltinOrders();
    $orders = ipull($orders, 'name');

    $form->appendControl(
      id(new AphrontFormSelectControl())
        ->setLabel(pht('Order'))
        ->setName('order')
        ->setOptions($orders)
        ->setValue($saved->getParameter('order')));
  }

/* -(  Paging and Executing Queries  )--------------------------------------- */


  public function getPageSize(PhabricatorSavedQuery $saved) {
    return $saved->getParameter('limit', 100);
  }


  public function shouldUseOffsetPaging() {
    return false;
  }


  public function newPagerForSavedQuery(PhabricatorSavedQuery $saved) {
    if ($this->shouldUseOffsetPaging()) {
      $pager = new AphrontPagerView();
    } else {
      $pager = new AphrontCursorPagerView();
    }

    $page_size = $this->getPageSize($saved);
    if (is_finite($page_size)) {
      $pager->setPageSize($page_size);
    } else {
      // Consider an INF pagesize to mean a large finite pagesize.

      // TODO: It would be nice to handle this more gracefully, but math
      // with INF seems to vary across PHP versions, systems, and runtimes.
      $pager->setPageSize(0xFFFF);
    }

    return $pager;
  }


  public function executeQuery(
    PhabricatorPolicyAwareQuery $query,
    AphrontView $pager) {

    $query->setViewer($this->requireViewer());

    if ($this->shouldUseOffsetPaging()) {
      $objects = $query->executeWithOffsetPager($pager);
    } else {
      $objects = $query->executeWithCursorPager($pager);
    }

    return $objects;
  }


/* -(  Rendering  )---------------------------------------------------------- */


  public function setRequest(AphrontRequest $request) {
    $this->request = $request;
    return $this;
  }

  public function getRequest() {
    return $this->request;
  }

  public function renderResults(
    array $objects,
    PhabricatorSavedQuery $query) {

    $phids = $this->getRequiredHandlePHIDsForResultList($objects, $query);

    if ($phids) {
      $handles = id(new PhabricatorHandleQuery())
        ->setViewer($this->requireViewer())
        ->witHPHIDs($phids)
        ->execute();
    } else {
      $handles = array();
    }

    return $this->renderResultList($objects, $query, $handles);
  }

  protected function getRequiredHandlePHIDsForResultList(
    array $objects,
    PhabricatorSavedQuery $query) {
    return array();
  }

  protected function renderResultList(
    array $objects,
    PhabricatorSavedQuery $query,
    array $handles) {
    throw new Exception(pht('Not supported here yet!'));
  }


/* -(  Application Search  )------------------------------------------------- */


  /**
   * Retrieve an object to use to define custom fields for this search.
   *
   * To integrate with custom fields, subclasses should override this method
   * and return an instance of the application object which implements
   * @{interface:PhabricatorCustomFieldInterface}.
   *
   * @return PhabricatorCustomFieldInterface|null Object with custom fields.
   * @task appsearch
   */
  public function getCustomFieldObject() {
    return null;
  }


  /**
   * Get the custom fields for this search.
   *
   * @return PhabricatorCustomFieldList|null Custom fields, if this search
   *   supports custom fields.
   * @task appsearch
   */
  public function getCustomFieldList() {
    if ($this->customFields === false) {
      $object = $this->getCustomFieldObject();
      if ($object) {
        $fields = PhabricatorCustomField::getObjectFields(
          $object,
          PhabricatorCustomField::ROLE_APPLICATIONSEARCH);
        $fields->setViewer($this->requireViewer());
      } else {
        $fields = null;
      }
      $this->customFields = $fields;
    }
    return $this->customFields;
  }


  /**
   * Moves data from the request into a saved query.
   *
   * @param AphrontRequest Request to read.
   * @param PhabricatorSavedQuery Query to write to.
   * @return void
   * @task appsearch
   */
  protected function readCustomFieldsFromRequest(
    AphrontRequest $request,
    PhabricatorSavedQuery $saved) {

    $list = $this->getCustomFieldList();
    if (!$list) {
      return;
    }

    foreach ($list->getFields() as $field) {
      $key = $this->getKeyForCustomField($field);
      $value = $field->readApplicationSearchValueFromRequest(
        $this,
        $request);
      $saved->setParameter($key, $value);
    }
  }


  /**
   * Applies data from a saved query to an executable query.
   *
   * @param PhabricatorCursorPagedPolicyAwareQuery Query to constrain.
   * @param PhabricatorSavedQuery Saved query to read.
   * @return void
   */
  protected function applyCustomFieldsToQuery(
    PhabricatorCursorPagedPolicyAwareQuery $query,
    PhabricatorSavedQuery $saved) {

    $list = $this->getCustomFieldList();
    if (!$list) {
      return;
    }

    foreach ($list->getFields() as $field) {
      $key = $this->getKeyForCustomField($field);
      $value = $field->applyApplicationSearchConstraintToQuery(
        $this,
        $query,
        $saved->getParameter($key));
    }
  }

  protected function applyOrderByToQuery(
    PhabricatorCursorPagedPolicyAwareQuery $query,
    array $standard_values,
    $order) {

    if (substr($order, 0, 7) === 'custom:') {
      $list = $this->getCustomFieldList();
      if (!$list) {
        $query->setOrderBy(head($standard_values));
        return;
      }

      foreach ($list->getFields() as $field) {
        $key = $this->getKeyForCustomField($field);

        if ($key === $order) {
          $index = $field->buildOrderIndex();

          if ($index === null) {
            $query->setOrderBy(head($standard_values));
            return;
          }

          $query->withApplicationSearchOrder(
            $field,
            $index,
            false);
          break;
        }
      }
    } else {
      $order = idx($standard_values, $order);
      if ($order) {
        $query->setOrderBy($order);
      } else {
        $query->setOrderBy(head($standard_values));
      }
    }
  }


  protected function getCustomFieldOrderOptions() {
    $list = $this->getCustomFieldList();
    if (!$list) {
      return;
    }

    $custom_order = array();
    foreach ($list->getFields() as $field) {
      if ($field->shouldAppearInApplicationSearch()) {
        if ($field->buildOrderIndex() !== null) {
          $key = $this->getKeyForCustomField($field);
          $custom_order[$key] = $field->getFieldName();
        }
      }
    }

    return $custom_order;
  }

  /**
   * Get a unique key identifying a field.
   *
   * @param PhabricatorCustomField Field to identify.
   * @return string Unique identifier, suitable for use as an input name.
   */
  public function getKeyForCustomField(PhabricatorCustomField $field) {
    return 'custom:'.$field->getFieldIndex();
  }


  /**
   * Add inputs to an application search form so the user can query on custom
   * fields.
   *
   * @param AphrontFormView Form to update.
   * @param PhabricatorSavedQuery Values to prefill.
   * @return void
   */
  protected function appendCustomFieldsToForm(
    AphrontFormView $form,
    PhabricatorSavedQuery $saved) {

    $list = $this->getCustomFieldList();
    if (!$list) {
      return;
    }

    $phids = array();
    foreach ($list->getFields() as $field) {
      $key = $this->getKeyForCustomField($field);
      $value = $saved->getParameter($key);
      $phids[$key] = $field->getRequiredHandlePHIDsForApplicationSearch($value);
    }
    $all_phids = array_mergev($phids);

    $handles = array();
    if ($all_phids) {
      $handles = id(new PhabricatorHandleQuery())
        ->setViewer($this->requireViewer())
        ->withPHIDs($all_phids)
        ->execute();
    }

    foreach ($list->getFields() as $field) {
      $key = $this->getKeyForCustomField($field);
      $value = $saved->getParameter($key);
      $field->appendToApplicationSearchForm(
        $this,
        $form,
        $value,
        array_select_keys($handles, $phids[$key]));
    }
  }

}
