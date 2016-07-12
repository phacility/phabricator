<?php

final class DrydockResourceListController extends DrydockResourceController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    $engine = new DrydockResourceSearchEngine();

    $id = $request->getURIData('id');
    if ($id) {
      $blueprint = id(new DrydockBlueprintQuery())
        ->setViewer($viewer)
        ->withIDs(array($id))
        ->executeOne();
      if (!$blueprint) {
        return new Aphront404Response();
      }
      $this->setBlueprint($blueprint);
      $engine->setBlueprint($blueprint);
    }

    $querykey = $request->getURIData('queryKey');

    $controller = id(new PhabricatorApplicationSearchController())
      ->setQueryKey($querykey)
      ->setSearchEngine($engine)
      ->setNavigation($this->buildSideNavView());

    return $this->delegateToController($controller);
  }

}
