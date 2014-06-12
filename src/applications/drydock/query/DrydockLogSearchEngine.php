<?php

final class DrydockLogSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('Drydock Logs');
  }

  public function getApplicationClassName() {
    return 'PhabricatorApplicationDrydock';
  }

  public function buildSavedQueryFromRequest(AphrontRequest $request) {
    $saved = new PhabricatorSavedQuery();

    return $saved;
  }

  public function buildQueryFromSavedQuery(PhabricatorSavedQuery $saved) {
    $query = id(new DrydockLogQuery());

    return $query;
  }

  public function buildSearchForm(
    AphrontFormView $form,
    PhabricatorSavedQuery $saved) {

  }

  protected function getURI($path) {
    return '/drydock/log/'.$path;
  }

  public function getBuiltinQueryNames() {
    $names = array(
      'all' => pht('All Logs'),
    );

    return $names;
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
