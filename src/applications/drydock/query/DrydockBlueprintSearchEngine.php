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

    if ($map['match'] !== null) {
      $query->withNameNgrams($map['match']);
    }

    if ($map['isDisabled'] !== null) {
      $query->withDisabled($map['isDisabled']);
    }

    return $query;
  }

  protected function buildCustomSearchFields() {
    return array(
      id(new PhabricatorSearchTextField())
        ->setLabel(pht('Name Contains'))
        ->setKey('match')
        ->setDescription(pht('Search for blueprints by name substring.')),
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

    if ($blueprints) {
      $edge_query = id(new PhabricatorEdgeQuery())
        ->withSourcePHIDs(mpull($blueprints, 'getPHID'))
        ->withEdgeTypes(
          array(
            PhabricatorProjectObjectHasProjectEdgeType::EDGECONST,
          ));

      $edge_query->execute();
    }

    $view = new PHUIObjectItemListView();

    foreach ($blueprints as $blueprint) {
      $impl = $blueprint->getImplementation();

      $item = id(new PHUIObjectItemView())
        ->setHeader($blueprint->getBlueprintName())
        ->setHref($blueprint->getURI())
        ->setObjectName(pht('Blueprint %d', $blueprint->getID()));

      if (!$impl->isEnabled()) {
        $item->setDisabled(true);
        $item->addIcon('fa-chain-broken grey', pht('Implementation'));
      }

      if ($blueprint->getIsDisabled()) {
        $item->setDisabled(true);
        $item->addIcon('fa-ban grey', pht('Disabled'));
      }

      $impl_icon = $impl->getBlueprintIcon();
      $impl_name = $impl->getBlueprintName();

      $impl_icon = id(new PHUIIconView())
        ->setIcon($impl_icon, 'lightgreytext');

      $item->addAttribute(array($impl_icon, ' ', $impl_name));

      $phid = $blueprint->getPHID();
      $project_phids = $edge_query->getDestinationPHIDs(array($phid));
      if ($project_phids) {
        $project_handles = $viewer->loadHandles($project_phids);
        $item->addAttribute(
          id(new PHUIHandleTagListView())
            ->setLimit(4)
            ->setSlim(true)
            ->setHandles($project_handles));
      }

      $view->addItem($item);
    }

    $result = new PhabricatorApplicationSearchResultView();
    $result->setObjectList($view);
    $result->setNoDataString(pht('No blueprints found.'));

    return $result;
  }

}
