<?php

final class HarbormasterBuildPlanSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('Harbormaster Build Plans');
  }

  public function getApplicationClassName() {
    return 'PhabricatorHarbormasterApplication';
  }

  public function newQuery() {
    return new HarbormasterBuildPlanQuery();
  }

  protected function buildCustomSearchFields() {
    return array(
      id(new PhabricatorSearchTextField())
        ->setLabel(pht('Name Contains'))
        ->setKey('match')
        ->setDescription(pht('Search for namespaces by name substring.')),
      id(new PhabricatorSearchCheckboxesField())
        ->setLabel(pht('Status'))
        ->setKey('status')
        ->setAliases(array('statuses'))
        ->setOptions(
          array(
            HarbormasterBuildPlan::STATUS_ACTIVE => pht('Active'),
            HarbormasterBuildPlan::STATUS_DISABLED => pht('Disabled'),
          )),
    );
  }

  protected function buildQueryFromParameters(array $map) {
    $query = $this->newQuery();

    if ($map['match'] !== null) {
      $query->withNameNgrams($map['match']);
    }

    if ($map['status']) {
      $query->withStatuses($map['status']);
    }

    return $query;
  }

  protected function getURI($path) {
    return '/harbormaster/plan/'.$path;
  }

  protected function getBuiltinQueryNames() {
    return array(
      'active' => pht('Active Plans'),
      'all' => pht('All Plans'),
    );
  }

  public function buildSavedQueryFromBuiltin($query_key) {
    $query = $this->newSavedQuery();
    $query->setQueryKey($query_key);

    switch ($query_key) {
      case 'active':
        return $query->setParameter(
          'status',
          array(
            HarbormasterBuildPlan::STATUS_ACTIVE,
          ));
      case 'all':
        return $query;
    }

    return parent::buildSavedQueryFromBuiltin($query_key);
  }

  protected function renderResultList(
    array $plans,
    PhabricatorSavedQuery $query,
    array $handles) {
    assert_instances_of($plans, 'HarbormasterBuildPlan');

    $viewer = $this->requireViewer();

    if ($plans) {
      $edge_query = id(new PhabricatorEdgeQuery())
        ->withSourcePHIDs(mpull($plans, 'getPHID'))
        ->withEdgeTypes(
          array(
            PhabricatorProjectObjectHasProjectEdgeType::EDGECONST,
          ));

      $edge_query->execute();
    }

    $list = new PHUIObjectItemListView();
    foreach ($plans as $plan) {
      $id = $plan->getID();

      $item = id(new PHUIObjectItemView())
        ->setObjectName(pht('Plan %d', $id))
        ->setHeader($plan->getName());

      if ($plan->isDisabled()) {
        $item->setDisabled(true);
      }

      if ($plan->isAutoplan()) {
        $item->addIcon('fa-lock grey', pht('Autoplan'));
      }

      $item->setHref($this->getApplicationURI("plan/{$id}/"));

      $phid = $plan->getPHID();
      $project_phids = $edge_query->getDestinationPHIDs(array($phid));
      $project_handles = $viewer->loadHandles($project_phids);

      $item->addAttribute(
        id(new PHUIHandleTagListView())
          ->setLimit(4)
          ->setNoDataString(pht('No Projects'))
          ->setSlim(true)
          ->setHandles($project_handles));

      $list->addItem($item);
    }

    $result = new PhabricatorApplicationSearchResultView();
    $result->setObjectList($list);
    $result->setNoDataString(pht('No build plans found.'));

    return $result;

  }

}
