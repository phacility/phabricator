<?php

final class DrydockBlueprintSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('Drydock Blueprints');
  }

  public function getApplicationClassName() {
    return 'PhabricatorDrydockApplication';
  }

  public function newQuery() {
    return id(new DrydockBlueprintQuery());
  }

  protected function buildQueryFromParameters(array $map) {
    $query = $this->newQuery();

    if ($map['isDisabled'] !== null) {
      $query->withDisabled($map['isDisabled']);
    }

    return $query;
  }

  protected function buildCustomSearchFields() {
    return array(
      id(new PhabricatorSearchThreeStateField())
        ->setLabel(pht('Disabled'))
        ->setKey('isDisabled')
        ->setOptions(
          pht('(Show All)'),
          pht('Show Only Disabled Blueprints'),
          pht('Hide Disabled Blueprints')),
    );
  }

  protected function getURI($path) {
    return '/drydock/blueprint/'.$path;
  }

  protected function getBuiltinQueryNames() {
    return array(
      'active' => pht('Active Blueprints'),
      'all' => pht('All Blueprints'),
    );
  }

  public function buildSavedQueryFromBuiltin($query_key) {
    $query = $this->newSavedQuery();
    $query->setQueryKey($query_key);

    switch ($query_key) {
      case 'active':
        return $query->setParameter('isDisabled', false);
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
        $item->addIcon('fa-chain-broken grey', pht('Implementation'));
      }

      if ($blueprint->getIsDisabled()) {
        $item->setDisabled(true);
        $item->addIcon('fa-ban grey', pht('Disabled'));
      }

      $item->addAttribute($blueprint->getImplementation()->getBlueprintName());

      $view->addItem($item);
    }

    $result = new PhabricatorApplicationSearchResultView();
    $result->setObjectList($view);
    $result->setNoDataString(pht('No blueprints found.'));

    return $result;
  }

}
