<?php

final class PhabricatorDashboardSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('Dashboards');
  }

  public function getApplicationClassName() {
    return 'PhabricatorDashboardApplication';
  }

  public function buildSavedQueryFromRequest(AphrontRequest $request) {
    return new PhabricatorSavedQuery();
  }

  public function buildQueryFromSavedQuery(PhabricatorSavedQuery $saved) {
    return new PhabricatorDashboardQuery();
  }

  public function buildSearchForm(
    AphrontFormView $form,
    PhabricatorSavedQuery $saved_query) {
    return;
  }

  protected function getURI($path) {
    return '/dashboard/'.$path;
  }

  public function getBuiltinQueryNames() {
    return array(
      'all' => pht('All Dashboards'),
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
    array $dashboards,
    PhabricatorSavedQuery $query,
    array $handles) {

    $viewer = $this->requireViewer();

    $list = new PHUIObjectItemListView();
    $list->setUser($viewer);
    foreach ($dashboards as $dashboard) {
      $id = $dashboard->getID();

      $item = id(new PHUIObjectItemView())
        ->setObjectName(pht('Dashboard %d', $id))
        ->setHeader($dashboard->getName())
        ->setHref($this->getApplicationURI("view/{$id}/"))
        ->setObject($dashboard);

      $list->addItem($item);
    }

    return $list;
  }

}
