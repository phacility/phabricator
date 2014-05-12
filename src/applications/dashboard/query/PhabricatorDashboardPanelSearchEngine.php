<?php

final class PhabricatorDashboardPanelSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getApplicationClassName() {
    return 'PhabricatorApplicationDashboard';
  }

  public function buildSavedQueryFromRequest(AphrontRequest $request) {
    $saved = new PhabricatorSavedQuery();

    return $saved;
  }

  public function buildQueryFromSavedQuery(PhabricatorSavedQuery $saved) {
    $query = id(new PhabricatorDashboardPanelQuery());

    return $query;
  }

  public function buildSearchForm(
    AphrontFormView $form,
    PhabricatorSavedQuery $saved_query) {
    return;
  }

  protected function getURI($path) {
    return '/dashboard/panel/'.$path;
  }

  public function getBuiltinQueryNames() {
    $names = array(
      'all' => pht('All Panels'),
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
    array $panels,
    PhabricatorSavedQuery $query,
    array $handles) {

    $viewer = $this->requireViewer();

    $list = new PHUIObjectItemListView();
    $list->setUser($viewer);
    foreach ($panels as $panel) {
      $item = id(new PHUIObjectItemView())
        ->setObjectName($panel->getMonogram())
        ->setHeader($panel->getName())
        ->setHref('/'.$panel->getMonogram())
        ->setObject($panel);

      $list->addItem($item);
    }

    return $list;
  }

}
