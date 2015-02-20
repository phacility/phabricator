<?php

final class DrydockLeaseSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('Drydock Leases');
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
    $query = id(new DrydockLeaseQuery());

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
    foreach (DrydockLeaseStatus::getAllStatuses() as $status) {
      $status_control->addCheckbox(
        'statuses[]',
        $status,
        DrydockLeaseStatus::getNameForStatus($status),
        in_array($status, $statuses));
    }

    $form
      ->appendChild($status_control);

  }

  protected function getURI($path) {
    return '/drydock/lease/'.$path;
  }

  protected function getBuiltinQueryNames() {
    return array(
      'active' => pht('Active Leases'),
      'all' => pht('All Leases'),
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
            DrydockLeaseStatus::STATUS_PENDING,
            DrydockLeaseStatus::STATUS_ACQUIRING,
            DrydockLeaseStatus::STATUS_ACTIVE,
          ));
      case 'all':
        return $query;
    }

    return parent::buildSavedQueryFromBuiltin($query_key);
  }

  protected function renderResultList(
    array $leases,
    PhabricatorSavedQuery $saved,
    array $handles) {

    return id(new DrydockLeaseListView())
      ->setUser($this->requireViewer())
      ->setLeases($leases)
      ->render();
  }

}
