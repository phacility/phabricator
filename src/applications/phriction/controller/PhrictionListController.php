<?php

final class PhrictionListController
  extends PhrictionController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $querykey = $request->getURIData('queryKey');

    $controller = id(new PhabricatorApplicationSearchController())
      ->setQueryKey($querykey)
      ->setSearchEngine(new PhrictionSearchEngine())
      ->setNavigation($this->buildSideNavView());

    return $this->delegateToController($controller);
  }

}
