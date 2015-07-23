<?php

final class FundInitiativeSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('Fund Initiatives');
  }

  public function getApplicationClassName() {
    return 'PhabricatorFundApplication';
  }

  public function buildSavedQueryFromRequest(AphrontRequest $request) {
    $saved = new PhabricatorSavedQuery();

    $saved->setParameter(
      'ownerPHIDs',
      $this->readUsersFromRequest($request, 'owners'));

    $saved->setParameter(
      'statuses',
      $this->readListFromRequest($request, 'statuses'));

    return $saved;
  }

  public function buildQueryFromSavedQuery(PhabricatorSavedQuery $saved) {
    $query = id(new FundInitiativeQuery())
      ->needProjectPHIDs(true);

    $owner_phids = $saved->getParameter('ownerPHIDs');
    if ($owner_phids) {
      $query->withOwnerPHIDs($owner_phids);
    }

    $statuses = $saved->getParameter('statuses');
    if ($statuses) {
      $query->withStatuses($statuses);
    }

    return $query;
  }

  public function buildSearchForm(
    AphrontFormView $form,
    PhabricatorSavedQuery $saved) {

    $statuses = $saved->getParameter('statuses', array());
    $statuses = array_fuse($statuses);

    $owner_phids = $saved->getParameter('ownerPHIDs', array());

    $status_map = FundInitiative::getStatusNameMap();
    $status_control = id(new AphrontFormCheckboxControl())
      ->setLabel(pht('Statuses'));
    foreach ($status_map as $status => $name) {
      $status_control->addCheckbox(
        'statuses[]',
        $status,
        $name,
        isset($statuses[$status]));
    }

    $form
      ->appendControl(
        id(new AphrontFormTokenizerControl())
          ->setLabel(pht('Owners'))
          ->setName('owners')
          ->setDatasource(new PhabricatorPeopleDatasource())
          ->setValue($owner_phids))
      ->appendChild($status_control);
  }

  protected function getURI($path) {
    return '/fund/'.$path;
  }

  protected function getBuiltinQueryNames() {
    $names = array();

    $names['open'] = pht('Open Initiatives');
    if ($this->requireViewer()->isLoggedIn()) {
      $names['owned'] = pht('Owned Initiatives');
    }
    $names['all'] = pht('All Initiatives');

    return $names;
  }

  public function buildSavedQueryFromBuiltin($query_key) {
    $query = $this->newSavedQuery();
    $query->setQueryKey($query_key);

    switch ($query_key) {
      case 'all':
        return $query;
      case 'owned':
        return $query->setParameter(
          'ownerPHIDs',
          array(
            $this->requireViewer()->getPHID(),
          ));
      case 'open':
        return $query->setParameter(
          'statuses',
          array(
            FundInitiative::STATUS_OPEN,
          ));
    }

    return parent::buildSavedQueryFromBuiltin($query_key);
  }

  protected function getRequiredHandlePHIDsForResultList(
    array $initiatives,
    PhabricatorSavedQuery $query) {

    $phids = array();
    foreach ($initiatives as $initiative) {
      $phids[] = $initiative->getOwnerPHID();
      foreach ($initiative->getProjectPHIDs() as $project_phid) {
        $phids[] = $project_phid;
      }
    }

    return $phids;
  }

  protected function renderResultList(
    array $initiatives,
    PhabricatorSavedQuery $query,
    array $handles) {
    assert_instances_of($initiatives, 'FundInitiative');

    $viewer = $this->requireViewer();

    $list = id(new PHUIObjectItemListView());
    foreach ($initiatives as $initiative) {
      $owner_handle = $handles[$initiative->getOwnerPHID()];

      $item = id(new PHUIObjectItemView())
        ->setObjectName($initiative->getMonogram())
        ->setHeader($initiative->getName())
        ->setHref('/'.$initiative->getMonogram())
        ->addByline(pht('Owner: %s', $owner_handle->renderLink()));

      if ($initiative->isClosed()) {
        $item->setDisabled(true);
      }

      $project_handles = array_select_keys(
        $handles,
        $initiative->getProjectPHIDs());
      if ($project_handles) {
        $item->addAttribute(
          id(new PHUIHandleTagListView())
            ->setLimit(4)
            ->setSlim(true)
            ->setHandles($project_handles));
      }

      $list->addItem($item);
    }

    $result = new PhabricatorApplicationSearchResultView();
    $result->setObjectList($list);
    $result->setNoDataString(pht('No initiatives found.'));

    return $result;


    return $list;
  }

}
