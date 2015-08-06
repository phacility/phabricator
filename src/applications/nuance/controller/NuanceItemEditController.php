<?php

final class NuanceItemEditController extends NuanceController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    if (!$id) {
      $item = new NuanceItem();
    } else {
      $item = id(new NuanceItemQuery())
        ->setViewer($viewer)
        ->withIDs(array($id))
        ->executeOne();
    }

    if (!$item) {
      return new Aphront404Response();
    }

    $crumbs = $this->buildApplicationCrumbs();
    $title = 'TODO';

    return $this->buildApplicationPage(
      $crumbs,
      array(
        'title' => $title,
      ));
  }

}
