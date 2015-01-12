<?php

final class FundInitiativeListController
  extends FundController {

  private $queryKey;

  public function shouldAllowPublic() {
    return true;
  }

  public function willProcessRequest(array $data) {
    $this->queryKey = idx($data, 'queryKey');
  }

  public function processRequest() {
    $controller = id(new PhabricatorApplicationSearchController())
      ->setQueryKey($this->queryKey)
      ->setSearchEngine(new FundInitiativeSearchEngine())
      ->setNavigation($this->buildSideNavView());

    return $this->delegateToController($controller);
  }

  public function buildSideNavView() {
    $user = $this->getRequest()->getUser();

    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI()));

    id(new FundInitiativeSearchEngine())
      ->setViewer($user)
      ->addNavigationItems($nav->getMenu());

    $nav->addLabel(pht('Backers'));
    $nav->addFilter('backers/', pht('Find Backers'));

    $nav->selectFilter(null);

    return $nav;
  }

  protected function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    $can_create = $this->hasApplicationCapability(
      FundCreateInitiativesCapability::CAPABILITY);

    $crumbs->addAction(
      id(new PHUIListItemView())
        ->setName(pht('Create Initiative'))
        ->setHref($this->getApplicationURI('create/'))
        ->setIcon('fa-plus-square')
        ->setDisabled(!$can_create)
        ->setWorkflow(!$can_create));

    return $crumbs;
  }

}
