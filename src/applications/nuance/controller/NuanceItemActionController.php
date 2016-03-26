<?php

final class NuanceItemActionController extends NuanceController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();
    $id = $request->getURIData('id');

    $item = id(new NuanceItemQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->executeOne();
    if (!$item) {
      return new Aphront404Response();
    }

    $action = $request->getURIData('action');

    $impl = $item->getImplementation();
    $impl->setViewer($viewer);
    $impl->setController($this);

    return $impl->buildActionResponse($item, $action);
  }

}
