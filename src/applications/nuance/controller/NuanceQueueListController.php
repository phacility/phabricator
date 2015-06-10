<?php

final class NuanceQueueListController
  extends NuanceController {

  public function handleRequest(AphrontRequest $request) {
    $request = $this->getRequest();
    $controller = id(new PhabricatorApplicationSearchController($request))
      ->setQueryKey($request->getURIData('queryKey'))
      ->setSearchEngine(new NuanceQueueSearchEngine())
      ->setNavigation($this->buildSideNavView());

    return $this->delegateToController($controller);
  }

  public function buildSideNavView($for_app = false) {
    $user = $this->getRequest()->getUser();

    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI()));

    id(new NuanceQueueSearchEngine())
      ->setViewer($user)
      ->addNavigationItems($nav->getMenu());

    $nav->selectFilter(null);

    return $nav;
  }

  protected function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    // TODO: Maybe use SourceManage capability?
    $can_create = true;

    $crumbs->addAction(
      id(new PHUIListItemView())
        ->setName(pht('Create Queue'))
        ->setHref($this->getApplicationURI('queue/new/'))
        ->setIcon('fa-plus-square')
        ->setDisabled(!$can_create)
        ->setWorkflow(!$can_create));

    return $crumbs;
  }

}
