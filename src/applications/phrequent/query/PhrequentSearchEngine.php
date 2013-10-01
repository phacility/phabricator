<?php

final class PhrequentSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getPageSize(PhabricatorSavedQuery $saved) {
    return $saved->getParameter('limit', 1000);
  }

  public function buildSavedQueryFromRequest(AphrontRequest $request) {
    $saved = new PhabricatorSavedQuery();

    $saved->setParameter(
      'userPHIDs',
      $this->readUsersFromRequest($request, 'users'));

    $saved->setParameter('ended', $request->getStr('ended'));

    $saved->setParameter('order', $request->getStr('order'));

    return $saved;
  }

  public function buildQueryFromSavedQuery(PhabricatorSavedQuery $saved) {
    $query = id(new PhrequentUserTimeQuery());

    $user_phids = $saved->getParameter('userPHIDs');
    if ($user_phids) {
      $query->withUserPHIDs($user_phids);
    }

    $ended = $saved->getParameter('ended');
    if ($ended != null) {
      $query->withEnded($ended);
    }

    $order = $saved->getParameter('order');
    if ($order != null) {
      $query->setOrder($order);
    }

    return $query;
  }

  public function buildSearchForm(
    AphrontFormView $form,
    PhabricatorSavedQuery $saved_query) {

    $user_phids = $saved_query->getParameter('userPHIDs', array());
    $ended = $saved_query->getParameter(
      'ended', PhrequentUserTimeQuery::ENDED_ALL);
    $order = $saved_query->getParameter(
      'order', PhrequentUserTimeQuery::ORDER_ENDED_DESC);

    $phids = array_merge($user_phids);
    $handles = id(new PhabricatorHandleQuery())
      ->setViewer($this->requireViewer())
      ->withPHIDs($phids)
      ->execute();

    $form
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setDatasource('/typeahead/common/users/')
          ->setName('users')
          ->setLabel(pht('Users'))
          ->setValue($handles))
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel(pht('Ended'))
          ->setName('ended')
          ->setValue($ended)
          ->setOptions(PhrequentUserTimeQuery::getEndedSearchOptions()))
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel(pht('Order'))
          ->setName('order')
          ->setValue($order)
          ->setOptions(PhrequentUserTimeQuery::getOrderSearchOptions()));
  }

  protected function getURI($path) {
    return '/phrequent/'.$path;
  }

  public function getBuiltinQueryNames() {
    $names = array(
      'tracking' => pht('Currently Tracking'),
      'all' => pht('All Tracked'),
    );

    return $names;
  }

  public function buildSavedQueryFromBuiltin($query_key) {

    $query = $this->newSavedQuery();
    $query->setQueryKey($query_key);

    switch ($query_key) {
      case 'all':
        return $query
          ->setParameter('order', PhrequentUserTimeQuery::ORDER_ENDED_DESC);
      case 'tracking':
        return $query
          ->setParameter('ended', PhrequentUserTimeQuery::ENDED_NO)
          ->setParameter('order', PhrequentUserTimeQuery::ORDER_ENDED_DESC);
    }

    return parent::buildSavedQueryFromBuiltin($query_key);
  }

}

