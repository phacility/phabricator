<?php

final class DrydockBlueprintListController extends DrydockBlueprintController
  implements PhabricatorApplicationSearchResultsControllerInterface {

  private $queryKey;

  public function shouldAllowPublic() {
    return true;
  }

  public function willProcessRequest(array $data) {
    $this->queryKey = idx($data, 'queryKey');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $controller = id(new PhabricatorApplicationSearchController($request))
      ->setQueryKey($this->queryKey)
      ->setSearchEngine(new DrydockBlueprintSearchEngine())
      ->setNavigation($this->buildSideNavView());

    return $this->delegateToController($controller);
  }

  public function renderResultsList(
    array $blueprints,
    PhabricatorSavedQuery $query) {
    assert_instances_of($blueprints, 'DrydockBlueprint');

    $viewer = $this->getRequest()->getUser();
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

  public function buildApplicationCrumbs() {
    $can_create = $this->hasApplicationCapability(
      DrydockCapabilityCreateBlueprints::CAPABILITY);

    $crumbs = parent::buildApplicationCrumbs();
    $crumbs->addAction(
      id(new PHUIListItemView())
        ->setName(pht('New Blueprint'))
        ->setHref($this->getApplicationURI('/blueprint/create/'))
        ->setDisabled(!$can_create)
        ->setWorkflow(!$can_create)
        ->setIcon('create'));
    return $crumbs;
  }

}
