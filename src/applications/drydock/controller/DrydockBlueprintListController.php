<?php

final class DrydockBlueprintListController extends DrydockBlueprintController {

  private $queryKey;

  public function shouldAllowPublic() {
    return true;
  }

  public function willProcessRequest(array $data) {
    $this->queryKey = idx($data, 'queryKey');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $controller = id(new PhabricatorApplicationSearchController())
      ->setQueryKey($this->queryKey)
      ->setSearchEngine(new DrydockBlueprintSearchEngine())
      ->setNavigation($this->buildSideNavView());

    return $this->delegateToController($controller);
  }

  protected function buildApplicationCrumbs() {
    $can_create = $this->hasApplicationCapability(
      DrydockCreateBlueprintsCapability::CAPABILITY);

    $crumbs = parent::buildApplicationCrumbs();
    $crumbs->addAction(
      id(new PHUIListItemView())
        ->setName(pht('New Blueprint'))
        ->setHref($this->getApplicationURI('/blueprint/create/'))
        ->setDisabled(!$can_create)
        ->setWorkflow(!$can_create)
        ->setIcon('fa-plus-square'));
    return $crumbs;
  }

}
