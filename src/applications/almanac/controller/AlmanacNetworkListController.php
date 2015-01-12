<?php

final class AlmanacNetworkListController
  extends AlmanacNetworkController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $controller = id(new PhabricatorApplicationSearchController())
      ->setQueryKey($request->getURIData('queryKey'))
      ->setSearchEngine(new AlmanacNetworkSearchEngine())
      ->setNavigation($this->buildSideNavView());

    return $this->delegateToController($controller);
  }

  protected function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    $can_create = $this->hasApplicationCapability(
      AlmanacCreateNetworksCapability::CAPABILITY);

    $crumbs->addAction(
      id(new PHUIListItemView())
        ->setName(pht('Create Network'))
        ->setHref($this->getApplicationURI('network/edit/'))
        ->setIcon('fa-plus-square')
        ->setDisabled(!$can_create)
        ->setWorkflow(!$can_create));

    return $crumbs;
  }

  public function buildSideNavView() {
    $viewer = $this->getViewer();

    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI()));

    id(new AlmanacNetworkSearchEngine())
      ->setViewer($viewer)
      ->addNavigationItems($nav->getMenu());

    $nav->selectFilter(null);

    return $nav;
  }


}
