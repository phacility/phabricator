<?php

final class HeraldRuleListController extends HeraldController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $querykey = $request->getURIData('queryKey');

    $controller = id(new PhabricatorApplicationSearchController())
      ->setQueryKey($querykey)
      ->setSearchEngine(new HeraldRuleSearchEngine())
      ->setNavigation($this->buildSideNavView());

    return $this->delegateToController($controller);
  }


}
