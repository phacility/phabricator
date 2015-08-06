<?php

final class NuanceRequestorEditController extends NuanceController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    if (!$id) {
      $requestor = new NuanceRequestor();

    } else {
      $requestor = id(new NuanceRequestorQuery())
        ->setViewer($viewer)
        ->withIDs(array($id))
        ->executeOne();
    }

    if (!$requestor) {
      return new Aphront404Response();
    }

    $crumbs = $this->buildApplicationCrumbs();
    $title = pht('TODO');

    return $this->buildApplicationPage(
      $crumbs,
      array(
        'title' => $title,
      ));
  }

}
