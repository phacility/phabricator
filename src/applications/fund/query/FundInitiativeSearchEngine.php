<?php

final class FundInitiativeSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('Fund Initiatives');
  }

  public function getApplicationClassName() {
    return 'PhabricatorFundApplication';
  }

  public function newQuery() {
    return new FundInitiativeQuery();
  }

  protected function buildCustomSearchFields() {
    return array(
      id(new PhabricatorUsersSearchField())
        ->setKey('ownerPHIDs')
        ->setAliases(array('owner', 'ownerPHID', 'owners'))
        ->setLabel(pht('Owners')),
      id(new PhabricatorSearchCheckboxesField())
        ->setKey('statuses')
        ->setLabel(pht('Statuses'))
        ->setOptions(FundInitiative::getStatusNameMap()),
    );
  }

  protected function buildQueryFromParameters(array $map) {
    $query = $this->newQuery();

    if ($map['ownerPHIDs']) {
      $query->withOwnerPHIDs($map['ownerPHIDs']);
    }

    if ($map['statuses']) {
      $query->withStatuses($map['statuses']);
    }

    return $query;
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

  protected function renderResultList(
    array $initiatives,
    PhabricatorSavedQuery $query,
    array $handles) {
    assert_instances_of($initiatives, 'FundInitiative');

    $viewer = $this->requireViewer();

    $load_phids = array();
    foreach ($initiatives as $initiative) {
      $load_phids[] = $initiative->getOwnerPHID();
    }

    if ($initiatives) {
      $edge_query = id(new PhabricatorEdgeQuery())
        ->withSourcePHIDs(mpull($initiatives, 'getPHID'))
        ->withEdgeTypes(
          array(
            PhabricatorProjectObjectHasProjectEdgeType::EDGECONST,
          ));

      $edge_query->execute();

      foreach ($edge_query->getDestinationPHIDs() as $phid) {
        $load_phids[] = $phid;
      }
    }

    $handles = $viewer->loadHandles($load_phids);
    $handles = iterator_to_array($handles);

    $list = new PHUIObjectItemListView();
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

      $project_phids = $edge_query->getDestinationPHIDs(
        array(
          $initiative->getPHID(),
        ));

      $project_handles = array_select_keys($handles, $project_phids);
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
  }

}
