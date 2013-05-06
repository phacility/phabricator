<?php

/**
 * Provides search functionality for the paste application.
 *
 * @group search
 */
final class PhabricatorPasteSearchEngine
  extends PhabricatorApplicationSearchEngine {

  protected $filter;
  protected $user;

  /**
   * Create a saved query object from the request.
   *
   * @param AphrontRequest The search request.
   * @return The saved query that is built.
   */
  public function buildSavedQueryFromRequest(AphrontRequest $request) {

    $saved = new PhabricatorSavedQuery();

    if ($this->filter == "my") {
      $user = $request->getUser();
      $saved->setParameter('authorPHIDs', array($user->getPHID()));
    } else {
      $data = $request->getRequestData();
      if (array_key_exists('set_users', $data)) {
        $saved->setParameter('authorPHIDs', $data['set_users']);
      }
    }

    try {
      $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
      $saved->save();
      unset($unguarded);
    } catch (AphrontQueryDuplicateKeyException $ex) {
      // Ignore, this is just a repeated search.
    }

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
  public function buildSearchForm(PhabricatorSavedQuery $saved_query) {
    $phids = $saved_query->getParameter('authorPHIDs', array());
    $handles = id(new PhabricatorObjectHandleData($phids))
      ->setViewer($this->user)
      ->loadHandles();
    $users_searched = mpull($handles, 'getFullName', 'getPHID');

    $form = id(new AphrontFormView())
      ->setUser($this->user);

    $form->appendChild(
      id(new AphrontFormTokenizerControl())
        ->setDatasource('/typeahead/common/searchowner/')
        ->setName('set_users')
        ->setLabel(pht('Users'))
        ->setValue($users_searched));

    $form->appendChild(
      id(new AphrontFormSubmitControl())
        ->setValue(pht('Filter Pastes')));

    return $form;
  }

  public function setPasteSearchFilter($filter) {
    $this->filter = $filter;
    return $this;
  }

  public function getPasteSearchFilter() {
    return $this->filter;
  }

  public function setPasteSearchUser($user) {
    $this->user = $user;
    return $this;
  }

}
