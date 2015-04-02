<?php

final class PhrequentSearchEngine extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('Phrequent Time');
  }

  public function getApplicationClassName() {
    return 'PhabricatorPhrequentApplication';
  }

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
    $query = id(new PhrequentUserTimeQuery())
      ->needPreemptingEvents(true);

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

    $form
      ->appendControl(
        id(new AphrontFormTokenizerControl())
          ->setDatasource(new PhabricatorPeopleDatasource())
          ->setName('users')
          ->setLabel(pht('Users'))
          ->setValue($user_phids))
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

  protected function getBuiltinQueryNames() {
    return array(
      'tracking' => pht('Currently Tracking'),
      'all' => pht('All Tracked'),
    );
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

  protected function getRequiredHandlePHIDsForResultList(
    array $usertimes,
    PhabricatorSavedQuery $query) {
    return array_mergev(
      array(
        mpull($usertimes, 'getUserPHID'),
        mpull($usertimes, 'getObjectPHID'),
      ));
  }

  protected function renderResultList(
    array $usertimes,
    PhabricatorSavedQuery $query,
    array $handles) {
    assert_instances_of($usertimes, 'PhrequentUserTime');
    $viewer = $this->requireViewer();

    $view = id(new PHUIObjectItemListView())
      ->setUser($viewer);

    foreach ($usertimes as $usertime) {
      $item = new PHUIObjectItemView();
      if ($usertime->getObjectPHID() === null) {
        $item->setHeader($usertime->getNote());
      } else {
        $obj = $handles[$usertime->getObjectPHID()];
        $item->setHeader($obj->getLinkName());
        $item->setHref($obj->getURI());
      }
      $item->setObject($usertime);

      $item->addByline(
        pht(
          'Tracked: %s',
          $handles[$usertime->getUserPHID()]->renderLink()));

      $started_date = phabricator_date($usertime->getDateStarted(), $viewer);
      $item->addIcon('none', $started_date);

      $block = new PhrequentTimeBlock(array($usertime));
      $time_spent = $block->getTimeSpentOnObject(
        $usertime->getObjectPHID(),
        PhabricatorTime::getNow());

      $time_spent = $time_spent == 0 ? 'none' :
        phutil_format_relative_time_detailed($time_spent);

      if ($usertime->getDateEnded() !== null) {
        $item->addAttribute(
          pht(
            'Tracked %s',
            $time_spent));
        $item->addAttribute(
          pht(
            'Ended on %s',
            phabricator_datetime($usertime->getDateEnded(), $viewer)));
      } else {
        $item->addAttribute(
          pht(
            'Tracked %s so far',
            $time_spent));
        if ($usertime->getObjectPHID() !== null &&
            $usertime->getUserPHID() === $viewer->getPHID()) {
          $item->addAction(
            id(new PHUIListItemView())
              ->setIcon('fa-stop')
              ->addSigil('phrequent-stop-tracking')
              ->setWorkflow(true)
              ->setRenderNameAsTooltip(true)
              ->setName(pht('Stop'))
              ->setHref(
                '/phrequent/track/stop/'.
                $usertime->getObjectPHID().'/'));
        }
        $item->setBarColor('green');
      }

      $view->addItem($item);
    }

    return $view;
  }

}
