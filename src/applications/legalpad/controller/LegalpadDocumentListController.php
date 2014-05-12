<?php

final class LegalpadDocumentListController extends LegalpadController {

  private $queryKey;

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

}
