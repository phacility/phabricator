<?php

final class DrydockLogListController extends DrydockLogController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $engine = new DrydockLogSearchEngine();

    $id = $request->getURIData('id');
    $type = $request->getURIData('type');
    switch ($type) {
      case 'blueprint':
        $blueprint = id(new DrydockBlueprintQuery())
          ->setViewer($viewer)
          ->withIDs(array($id))
          ->executeOne();
        if (!$blueprint) {
          return new Aphront404Response();
        }
        $engine->setBlueprint($blueprint);
        $this->setBlueprint($blueprint);
        break;
      case 'resource':
        $resource = id(new DrydockResourceQuery())
          ->setViewer($viewer)
          ->withIDs(array($id))
          ->executeOne();
        if (!$resource) {
          return new Aphront404Response();
        }
        $engine->setResource($resource);
        $this->setResource($resource);
        break;
      case 'lease':
        $lease = id(new DrydockLeaseQuery())
          ->setViewer($viewer)
          ->withIDs(array($id))
          ->executeOne();
        if (!$lease) {
          return new Aphront404Response();
        }
        $engine->setLease($lease);
        $this->setLease($lease);
        break;
      default:
        return new Aphront404Response();
    }

    $query_key = $request->getURIData('queryKey');

    $controller = id(new PhabricatorApplicationSearchController())
      ->setQueryKey($query_key)
      ->setSearchEngine($engine)
      ->setNavigation($this->buildSideNavView());

    return $this->delegateToController($controller);
  }

}
