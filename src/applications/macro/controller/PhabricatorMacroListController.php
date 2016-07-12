<?php

final class PhabricatorMacroListController extends PhabricatorMacroController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $key = $request->getURIData('key');

    $controller = id(new PhabricatorApplicationSearchController())
      ->setQueryKey($key)
      ->setSearchEngine(new PhabricatorMacroSearchEngine())
      ->setNavigation($this->buildSideNavView());

    return $this->delegateToController($controller);
  }

}
