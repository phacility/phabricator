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

    $list = new PHUIObjectItemListView();
    foreach ($plans as $plan) {
      $id = $plan->getID();

      $item = id(new PHUIObjectItemView())
        ->setObjectName(pht('Plan %d', $plan->getID()))
        ->setHeader($plan->getName());

      if ($plan->isDisabled()) {
        $item->setDisabled(true);
      }

      if ($plan->isAutoplan()) {
        $item->addIcon('fa-lock grey', pht('Autoplan'));
      }

      $item->setHref($this->getApplicationURI("plan/{$id}/"));

      $list->addItem($item);
    }

    return $list;
  }

}
