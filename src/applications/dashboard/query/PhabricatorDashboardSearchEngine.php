<?php

final class PhabricatorDashboardSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getApplicationClassName() {
    return 'PhabricatorApplicationDashboard';
  }

  public function buildSavedQueryFromRequest(AphrontRequest $request) {
    $saved = new PhabricatorSavedQuery();

    return $saved;
  }

  public function buildQueryFromSavedQuery(PhabricatorSavedQuery $saved) {
    $query = id(new PhabricatorDashboardQuery());

    return $query;
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
    $names = array(
      'all' => pht('All Dashboards'),
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
