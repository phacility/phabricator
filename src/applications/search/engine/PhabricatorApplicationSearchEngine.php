<?php

/**
 * Represents an abstract search engine for an application. It supports
 * creating and storing saved queries.
 *
 * @task builtin  Builtin Queries
 * @task uri      Query URIs
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
   * @param AphrontFormView       Form to populate.
   * @param PhabricatorSavedQuery The query from which to build the form.
   * @return void
   */
  abstract public function buildSearchForm(
    AphrontFormView $form,
    PhabricatorSavedQuery $query);


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


  public function newSavedQuery() {
    return id(new PhabricatorSavedQuery())
      ->setEngineClassName(get_class($this));
  }


  public function addNavigationItems(AphrontSideNavFilterView $nav) {
    $viewer = $this->requireViewer();

    $nav->addLabel(pht('Queries'));

    $named_queries = id(new PhabricatorNamedQueryQuery())
      ->setViewer($viewer)
      ->withUserPHIDs(array($viewer->getPHID()))
      ->withEngineClassNames(array(get_class($this)))
      ->execute();

    $named_queries = $named_queries + $this->getBuiltinQueries($viewer);

    foreach ($named_queries as $query) {
      $key = $query->getQueryKey();
      $uri = $this->getQueryResultsPageURI($key);
      $nav->addFilter('query/'.$key, $query->getQueryName(), $uri);
    }

    $manage_uri = $this->getQueryManagementURI();
    $nav->addFilter('query/edit', pht('Edit Queries...'), $manage_uri);

    $nav->addLabel(pht('Search'));
    $advanced_uri = $this->getQueryResultsPageURI('advanced');
    $nav->addFilter('query/advanced', pht('Advanced Search'), $advanced_uri);

    return $this;
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
  public function getBuiltinQuery($query_key) {
    if (!$this->isBuiltinQuery($query_key)) {
      throw new Exception("'{$query_key}' is not a builtin!");
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
    throw new Exception("Builtin '{$query_key}' is not supported!");
  }
}
