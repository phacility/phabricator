<?php

final class LegalpadDocumentSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getApplicationClassName() {
    return 'PhabricatorApplicationLegalpad';
  }

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

    $form
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setDatasource('/typeahead/common/users/')
          ->setName('creators')
          ->setLabel(pht('Creators'))
          ->setValue(array_select_keys($handles, $creator_phids)))
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setDatasource('/typeahead/common/users/')
          ->setName('contributors')
          ->setLabel(pht('Contributors'))
          ->setValue(array_select_keys($handles, $contributor_phids)));

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

  protected function getRequiredHandlePHIDsForResultList(
    array $documents,
    PhabricatorSavedQuery $query) {
    return array_mergev(mpull($documents, 'getRecentContributorPHIDs'));
  }

  protected function renderResultList(
    array $documents,
    PhabricatorSavedQuery $query,
    array $handles) {
    assert_instances_of($documents, 'LegalpadDocument');

    $viewer = $this->requireViewer();

    $list = new PHUIObjectItemListView();
    $list->setUser($viewer);
    foreach ($documents as $document) {
      $last_updated = phabricator_date($document->getDateModified(), $viewer);
      $recent_contributors = $document->getRecentContributorPHIDs();
      $updater = $handles[reset($recent_contributors)]->renderLink();

      $title = $document->getTitle();

      $item = id(new PHUIObjectItemView())
        ->setObjectName('L'.$document->getID())
        ->setHeader($title)
        ->setHref($this->getApplicationURI('view/'.$document->getID()))
        ->setObject($document)
        ->addIcon('none', pht('Last updated: %s', $last_updated))
        ->addByline(pht('Updated by: %s', $updater))
        ->addAttribute(pht('Versions: %d', $document->getVersions()));

      $list->addItem($item);
    }

    return $list;
  }

}
