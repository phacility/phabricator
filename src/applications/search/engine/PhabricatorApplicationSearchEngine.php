<?php

/**
 * Represents an abstract search engine for an application. It supports
 * creating and storing saved queries.
 *
 * @task builtin  Builtin Queries
 *
 * @group search
 */
abstract class PhabricatorApplicationSearchEngine {

  private $viewer;

  public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  protected function requireViewer() {
    if (!$this->viewer) {
      throw new Exception("Call setViewer() before using an engine!");
    }
    return $this->viewer;
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
   * @param PhabricatorSavedQuery The query from which to build the form.
   * @return void
   */
  abstract public function buildSearchForm(PhabricatorSavedQuery $query);


  /**
   * Return an application URI corresponding to the results page of a query.
   * Normally, this is something like `/application/query/QUERYKEY/`.
   *
   * @param   PhabricatorSavedQuery   The query to build a URI for.
   * @return  string                  URI where the query can be executed.
   */
  abstract public function getQueryResultsPageURI(PhabricatorSavedQuery $query);


  public function newSavedQuery() {
    return id(new PhabricatorSavedQuery())
      ->setEngineClassName(get_class($this));
  }


/* -(  Builtin Queries  )---------------------------------------------------- */


  /**
   * @task builtin
   */
  public function getBuiltinQueries() {
    $names = $this->getBuiltinQueryNames();

    $queries = array();
    foreach ($names as $key => $name) {
      $queries[$key] = id(new PhabricatorNamedQuery())
        ->setQueryName($name)
        ->setQueryKey($key)
        ->setIsBuiltin(true)
        ->makeEphemeral();
    }

    return $queries;
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
    throw new Exception("Builtin '{$query_key}' is not supported!");
  }
}
