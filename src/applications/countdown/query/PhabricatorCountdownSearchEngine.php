<?php

final class PhabricatorCountdownSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('Countdowns');
  }

  public function getApplicationClassName() {
    return 'PhabricatorCountdownApplication';
  }

  public function buildSavedQueryFromRequest(AphrontRequest $request) {
    $saved = new PhabricatorSavedQuery();
    $saved->setParameter(
      'authorPHIDs',
      $this->readUsersFromRequest($request, 'authors'));

    $saved->setParameter('upcoming', $request->getBool('upcoming'));

    return $saved;
  }

  public function buildQueryFromSavedQuery(PhabricatorSavedQuery $saved) {
    $query = id(new PhabricatorCountdownQuery());

    $author_phids = $saved->getParameter('authorPHIDs', array());
    if ($author_phids) {
      $query->withAuthorPHIDs($author_phids);
    }

    if ($saved->getParameter('upcoming')) {
      $query->withUpcoming(true);
    }

    return $query;
  }

  public function buildSearchForm(
    AphrontFormView $form,
    PhabricatorSavedQuery $saved_query) {

    $author_phids = $saved_query->getParameter('authorPHIDs', array());
    $upcoming = $saved_query->getParameter('upcoming');

    $form
      ->appendControl(
        id(new AphrontFormTokenizerControl())
          ->setDatasource(new PhabricatorPeopleDatasource())
          ->setName('authors')
          ->setLabel(pht('Authors'))
          ->setValue($author_phids))
      ->appendChild(
        id(new AphrontFormCheckboxControl())
          ->addCheckbox(
            'upcoming',
            1,
            pht('Show only countdowns that are still counting down.'),
            $upcoming));
  }

  protected function getURI($path) {
    return '/countdown/'.$path;
  }

  protected function getBuiltinQueryNames() {
    $names = array(
      'upcoming' => pht('Upcoming'),
      'all' => pht('All'),
    );

    if ($this->requireViewer()->getPHID()) {
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
      case 'upcoming':
        return $query->setParameter('upcoming', true);
    }

    return parent::buildSavedQueryFromBuiltin($query_key);
  }

  protected function getRequiredHandlePHIDsForResultList(
    array $countdowns,
    PhabricatorSavedQuery $query) {

    return mpull($countdowns, 'getAuthorPHID');
  }

  protected function renderResultList(
    array $countdowns,
    PhabricatorSavedQuery $query,
    array $handles) {

    assert_instances_of($countdowns, 'PhabricatorCountdown');

    $viewer = $this->requireViewer();

    $list = new PHUIObjectItemListView();
    $list->setUser($viewer);
    foreach ($countdowns as $countdown) {
      $id = $countdown->getID();

      $item = id(new PHUIObjectItemView())
        ->setObjectName("C{$id}")
        ->setHeader($countdown->getTitle())
        ->setHref($this->getApplicationURI("{$id}/"))
        ->addByline(
          pht(
            'Created by %s',
            $handles[$countdown->getAuthorPHID()]->renderLink()));

      $epoch = $countdown->getEpoch();
      if ($epoch >= time()) {
        $item->addIcon(
          'none',
          pht('Ends %s', phabricator_datetime($epoch, $viewer)));
      } else {
        $item->addIcon(
          'delete',
          pht('Ended %s', phabricator_datetime($epoch, $viewer)));
        $item->setDisabled(true);
      }

      $list->addItem($item);
    }

    return $list;
  }

}
