<?php

final class NuanceItemViewController extends NuanceController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    $item = id(new NuanceItemQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->executeOne();

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
