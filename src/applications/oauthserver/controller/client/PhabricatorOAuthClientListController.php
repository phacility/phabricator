<?php

final class PhabricatorOAuthClientListController
  extends PhabricatorOAuthClientController {

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
      ->setSearchEngine(new PhabricatorOAuthServerClientSearchEngine())
      ->setNavigation($this->buildSideNavView());

    return $this->delegateToController($controller);
  }

  protected function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    $can_create = $this->hasApplicationCapability(
        PhabricatorOAuthServerCreateClientsCapability::CAPABILITY);

    $crumbs->addAction(
      id(new PHUIListItemView())
        ->setHref($this->getApplicationURI('client/create/'))
        ->setName(pht('Create Application'))
        ->setDisabled(!$can_create)
        ->setWorkflow(!$can_create)
        ->setIcon('fa-plus-square'));

    return $crumbs;
  }

}
