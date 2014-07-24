<?php

final class LegalpadDocumentListController extends LegalpadController {

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
      ->setSearchEngine(new LegalpadDocumentSearchEngine())
      ->setNavigation($this->buildSideNav());

    return $this->delegateToController($controller);
  }

  public function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    $can_create = $this->hasApplicationCapability(
      LegalpadCreateDocumentsCapability::CAPABILITY);

    $crumbs->addAction(
      id(new PHUIListItemView())
        ->setName(pht('Create Document'))
        ->setHref($this->getApplicationURI('create/'))
        ->setIcon('fa-plus-square')
        ->setDisabled(!$can_create)
        ->setWorkflow(!$can_create));


    return $crumbs;
  }

}
