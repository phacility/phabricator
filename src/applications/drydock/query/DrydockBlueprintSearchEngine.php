<?php

final class DrydockBlueprintSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('Drydock Blueprints');
  }

  public function getApplicationClassName() {
    return 'PhabricatorDrydockApplication';
  }

  public function buildSavedQueryFromRequest(AphrontRequest $request) {
    return new PhabricatorSavedQuery();
  }

  public function buildQueryFromSavedQuery(PhabricatorSavedQuery $saved) {
    return new DrydockBlueprintQuery();
  }

  public function buildSearchForm(
    AphrontFormView $form,
    PhabricatorSavedQuery $saved) {}

  protected function getURI($path) {
    return '/drydock/blueprint/'.$path;
  }

  protected function getBuiltinQueryNames() {
    return array(
      'all' => pht('All Blueprints'),
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
    array $blueprints,
    PhabricatorSavedQuery $query,
    array $handles) {
    assert_instances_of($blueprints, 'DrydockBlueprint');

    $viewer = $this->requireViewer();
    $view = new PHUIObjectItemListView();

    foreach ($blueprints as $blueprint) {
      $item = id(new PHUIObjectItemView())
        ->setHeader($blueprint->getBlueprintName())
        ->setHref($this->getApplicationURI('/blueprint/'.$blueprint->getID()))
        ->setObjectName(pht('Blueprint %d', $blueprint->getID()));

      if (!$blueprint->getImplementation()->isEnabled()) {
        $item->setDisabled(true);
      }

      $item->addAttribute($blueprint->getImplementation()->getBlueprintName());

      $view->addItem($item);
    }

    return $view;
  }

}
