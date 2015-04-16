<?php

final class HarbormasterBuildPlanSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('Harbormaster Build Plans');
  }

  public function getApplicationClassName() {
    return 'PhabricatorHarbormasterApplication';
  }

  public function buildSavedQueryFromRequest(AphrontRequest $request) {
    $saved = new PhabricatorSavedQuery();

    $saved->setParameter(
      'status',
      $this->readListFromRequest($request, 'status'));

    $this->saveQueryOrder($saved, $request);

    return $saved;
  }

  public function buildQueryFromSavedQuery(PhabricatorSavedQuery $saved) {
    $query = id(new HarbormasterBuildPlanQuery());
    $this->setQueryOrder($query, $saved);

    $status = $saved->getParameter('status', array());
    if ($status) {
      $query->withStatuses($status);
    }

    return $query;
  }

  public function buildSearchForm(
    AphrontFormView $form,
    PhabricatorSavedQuery $saved) {

    $status = $saved->getParameter('status', array());

    $form
      ->appendChild(
        id(new AphrontFormCheckboxControl())
          ->setLabel('Status')
          ->addCheckbox(
            'status[]',
            HarbormasterBuildPlan::STATUS_ACTIVE,
            pht('Active'),
            in_array(HarbormasterBuildPlan::STATUS_ACTIVE, $status))
          ->addCheckbox(
            'status[]',
            HarbormasterBuildPlan::STATUS_DISABLED,
            pht('Disabled'),
            in_array(HarbormasterBuildPlan::STATUS_DISABLED, $status)));

    $this->appendOrderFieldsToForm(
      $form,
      $saved,
      new HarbormasterBuildPlanQuery());
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

      $item->setHref($this->getApplicationURI("plan/{$id}/"));

      $list->addItem($item);
    }

    return $list;
  }

}
