<?php

final class DrydockLogSearchEngine extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('Drydock Logs');
  }

  public function getApplicationClassName() {
    return 'PhabricatorDrydockApplication';
  }

  public function buildSavedQueryFromRequest(AphrontRequest $request) {
    return new PhabricatorSavedQuery();
  }

  public function buildQueryFromSavedQuery(PhabricatorSavedQuery $saved) {
    return new DrydockLogQuery();
  }

  public function buildSearchForm(
    AphrontFormView $form,
    PhabricatorSavedQuery $saved) {}

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

    return id(new DrydockLogListView())
      ->setUser($this->requireViewer())
      ->setLogs($logs)
      ->render();
  }

}
