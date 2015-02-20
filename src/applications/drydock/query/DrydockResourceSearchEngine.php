<?php

final class DrydockResourceSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('Drydock Resources');
  }

  public function getApplicationClassName() {
    return 'PhabricatorDrydockApplication';
  }

  public function buildSavedQueryFromRequest(AphrontRequest $request) {
    $saved = new PhabricatorSavedQuery();

    $saved->setParameter(
      'statuses',
      $this->readListFromRequest($request, 'statuses'));

    return $saved;
  }

  public function buildQueryFromSavedQuery(PhabricatorSavedQuery $saved) {
    $query = id(new DrydockResourceQuery());

    $statuses = $saved->getParameter('statuses', array());
    if ($statuses) {
      $query->withStatuses($statuses);
    }

    return $query;
  }

  public function buildSearchForm(
    AphrontFormView $form,
    PhabricatorSavedQuery $saved) {

    $statuses = $saved->getParameter('statuses', array());

    $status_control = id(new AphrontFormCheckboxControl())
      ->setLabel(pht('Status'));
    foreach (DrydockResourceStatus::getAllStatuses() as $status) {
      $status_control->addCheckbox(
        'statuses[]',
        $status,
        DrydockResourceStatus::getNameForStatus($status),
        in_array($status, $statuses));
    }

    $form
      ->appendChild($status_control);
  }

  protected function getURI($path) {
    return '/drydock/resource/'.$path;
  }

  protected function getBuiltinQueryNames() {
    return array(
      'active' => pht('Active Resources'),
      'all' => pht('All Resources'),
    );
  }

  public function buildSavedQueryFromBuiltin($query_key) {
    $query = $this->newSavedQuery();
    $query->setQueryKey($query_key);

    switch ($query_key) {
      case 'active':
        return $query->setParameter(
          'statuses',
          array(
            DrydockResourceStatus::STATUS_PENDING,
            DrydockResourceStatus::STATUS_OPEN,
          ));
      case 'all':
        return $query;
    }

    return parent::buildSavedQueryFromBuiltin($query_key);
  }

  protected function renderResultList(
    array $resources,
    PhabricatorSavedQuery $query,
    array $handles) {

    return id(new DrydockResourceListView())
      ->setUser($this->requireViewer())
      ->setResources($resources)
      ->render();
  }

}
