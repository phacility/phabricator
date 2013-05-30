<?php

/**
 * Provides search functionality for the paste application.
 *
 * @group search
 */
final class PhabricatorPasteSearchEngine
  extends PhabricatorApplicationSearchEngine {

  /**
   * Create a saved query object from the request.
   *
   * @param AphrontRequest The search request.
   * @return The saved query that is built.
   */
  public function buildSavedQueryFromRequest(AphrontRequest $request) {
    $saved = new PhabricatorSavedQuery();
    $saved->setParameter(
      'authorPHIDs',
      array_values($request->getArr('authors')));

    return $saved;
  }

  /**
   * Executes the saved query.
   *
   * @param PhabricatorSavedQuery
   * @return The result of the query.
   */
  public function buildQueryFromSavedQuery(PhabricatorSavedQuery $saved) {
    $query = id(new PhabricatorPasteQuery())
      ->needContent(true)
      ->withIDs($saved->getParameter('ids', array()))
      ->withPHIDs($saved->getParameter('phids', array()))
      ->withAuthorPHIDs($saved->getParameter('authorPHIDs', array()))
      ->withParentPHIDs($saved->getParameter('parentPHIDs', array()));

    return $query;
  }

  /**
   * Builds the search form using the request.
   *
   * @param PhabricatorSavedQuery The query to populate the form with.
   * @return AphrontFormView The built form.
   */
  public function buildSearchForm(
    AphrontFormView $form,
    PhabricatorSavedQuery $saved_query) {
    $phids = $saved_query->getParameter('authorPHIDs', array());
    $handles = id(new PhabricatorObjectHandleData($phids))
      ->setViewer($this->requireViewer())
      ->loadHandles();
    $author_tokens = mpull($handles, 'getFullName', 'getPHID');

    $form->appendChild(
      id(new AphrontFormTokenizerControl())
        ->setDatasource('/typeahead/common/users/')
        ->setName('authors')
        ->setLabel(pht('Authors'))
        ->setValue($author_tokens));
  }

  protected function getURI($path) {
    return '/paste/'.$path;
  }

  public function getBuiltinQueryNames() {
    $names = array(
      'all'       => pht('All Pastes'),
    );

    if ($this->requireViewer()->isLoggedIn()) {
      $names['authored'] = pht('Authored');
    }

    return $names;
  }

  public function buildSavedQueryFromBuiltin($query_key) {

    $query = $this->newSavedQuery();
    $query->setQueryKey($query_key);

    switch ($query_key) {
      case 'all':
        return $query;
      case 'authored':
        return $query->setParameter(
          'authorPHIDs',
          array($this->requireViewer()->getPHID()));
    }

    return parent::buildSavedQueryFromBuiltin($query_key);
  }

}
