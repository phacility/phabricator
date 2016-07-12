<?php

final class DrydockLeaseListController extends DrydockLeaseController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $query_key = $request->getURIData('queryKey');

    $engine = new DrydockLeaseSearchEngine();

    $id = $request->getURIData('id');
    if ($id) {
      $resource = id(new DrydockResourceQuery())
        ->setViewer($viewer)
        ->withIDs(array($id))
        ->executeOne();
      if (!$resource) {
        return new Aphront404Response();
      }
      $this->setResource($resource);
      $engine->setResource($resource);
    }

    $controller = id(new PhabricatorApplicationSearchController())
      ->setQueryKey($query_key)
      ->setSearchEngine($engine)
      ->setNavigation($this->buildSideNavView());

    return $this->delegateToController($controller);
  }

}
