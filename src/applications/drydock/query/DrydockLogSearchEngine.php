<?php

final class DrydockLogSearchEngine extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('Drydock Logs');
  }

  public function getApplicationClassName() {
    return 'PhabricatorDrydockApplication';
  }

  public function buildSavedQueryFromRequest(AphrontRequest $request) {
    $query = new PhabricatorSavedQuery();

    $query->setParameter(
      'resourcePHIDs',
      $this->readListFromRequest($request, 'resources'));
    $query->setParameter(
      'leasePHIDs',
      $this->readListFromRequest($request, 'leases'));

    return $query;
  }

  public function buildQueryFromSavedQuery(PhabricatorSavedQuery $saved) {

    // TODO: Change logs to use PHIDs instead of IDs.
    $resource_ids = id(new DrydockResourceQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withPHIDs($saved->getParameter('resourcePHIDs', array()))
      ->execute();
    $resource_ids = mpull($resource_ids, 'getID');
    $lease_ids = id(new DrydockLeaseQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withPHIDs($saved->getParameter('leasePHIDs', array()))
      ->execute();
    $lease_ids = mpull($lease_ids, 'getID');

    return id(new DrydockLogQuery())
      ->withResourceIDs($resource_ids)
      ->withLeaseIDs($lease_ids);
  }

  public function buildSearchForm(
    AphrontFormView $form,
    PhabricatorSavedQuery $saved) {

    $form
      ->appendControl(
        id(new AphrontFormTokenizerControl())
          ->setDatasource(new DrydockResourceDatasource())
          ->setName('resources')
          ->setLabel(pht('Resources'))
          ->setValue($saved->getParameter('resourcePHIDs', array())))
      ->appendControl(
        id(new AphrontFormTokenizerControl())
          ->setDatasource(new DrydockLeaseDatasource())
          ->setName('leases')
          ->setLabel(pht('Leases'))
          ->setValue($saved->getParameter('leasePHIDs', array())));
  }

  protected function getURI($path) {
    return '/drydock/log/'.$path;
  }

  protected function getBuiltinQueryNames() {
    return array(
      'all' => pht('All Logs'),
    );
  }

  public function buildSavedQueryFromBuiltin($query_key) {
    $query = $this->newSavedQuery();
    $query->setQueryKey($query_key);

    switch ($query_key) {
      case 'all':
        return $query;
    }

    return parent::buildSavedQueryFromBuiltin($query_key);
  }

  protected function renderResultList(
    array $logs,
    PhabricatorSavedQuery $query,
    array $handles) {

    $list = id(new DrydockLogListView())
      ->setUser($this->requireViewer())
      ->setLogs($logs);

    $result = new PhabricatorApplicationSearchResultView();
    $result->setTable($list);

    return $result;
  }

}
