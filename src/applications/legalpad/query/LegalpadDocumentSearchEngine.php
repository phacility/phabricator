<?php

/**
 * @group legalpad
 */
final class LegalpadDocumentSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function buildSavedQueryFromRequest(AphrontRequest $request) {
    $saved = new PhabricatorSavedQuery();
    $saved->setParameter(
      'creatorPHIDs',
      $this->readUsersFromRequest($request, 'creators'));

    $saved->setParameter(
      'contributorPHIDs',
      $this->readUsersFromRequest($request, 'contributors'));

    $saved->setParameter('createdStart', $request->getStr('createdStart'));
    $saved->setParameter('createdEnd', $request->getStr('createdEnd'));

    return $saved;
  }

  public function buildQueryFromSavedQuery(PhabricatorSavedQuery $saved) {
    $query = id(new LegalpadDocumentQuery())
      ->withCreatorPHIDs($saved->getParameter('creatorPHIDs', array()))
      ->withContributorPHIDs($saved->getParameter('contributorPHIDs', array()));

    $start = $this->parseDateTime($saved->getParameter('createdStart'));
    $end = $this->parseDateTime($saved->getParameter('createdEnd'));

    if ($start) {
      $query->withDateCreatedAfter($start);
    }

    if ($end) {
      $query->withDateCreatedBefore($end);
    }

    return $query;
  }

  public function buildSearchForm(
    AphrontFormView $form,
    PhabricatorSavedQuery $saved_query) {

    $creator_phids = $saved_query->getParameter('creatorPHIDs', array());
    $contributor_phids = $saved_query->getParameter(
      'contributorPHIDs', array());
    $phids = array_merge($creator_phids, $contributor_phids);
    $handles = id(new PhabricatorHandleQuery())
      ->setViewer($this->requireViewer())
      ->withPHIDs($phids)
      ->execute();
    $tokens = mpull($handles, 'getFullName', 'getPHID');

    $form
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setDatasource('/typeahead/common/users/')
          ->setName('creators')
          ->setLabel(pht('Creators'))
          ->setValue(array_select_keys($tokens, $creator_phids)))
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setDatasource('/typeahead/common/users/')
          ->setName('contributors')
          ->setLabel(pht('Contributors'))
          ->setValue(array_select_keys($tokens, $contributor_phids)));

    $this->buildDateRange(
      $form,
      $saved_query,
      'createdStart',
      pht('Created After'),
      'createdEnd',
      pht('Created Before'));

  }

  protected function getURI($path) {
    return '/legalpad/'.$path;
  }

  public function getBuiltinQueryNames() {
    $names = array(
      'all' => pht('All Documents'),
    );

    return $names;
  }

  public function buildSavedQueryFromBuiltin($query_key) {

    $query = $this->newSavedQuery();
    $query->setQueryKey($query_key);

    switch ($query_key) {
      case 'all':
        return $query;
    }

    return parent::buildSavedQueryFromBuiltin($query_key);
  }

}
