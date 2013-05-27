<?php

/**
 * Represents an abstract search engine for an application. It supports
 * creating and storing saved queries.
 *
 * @group search
 */
abstract class PhabricatorApplicationSearchEngine {

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
}
