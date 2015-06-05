<?php

final class NuanceSourceActionController extends NuanceController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    $source = id(new NuanceSourceQuery())
      ->setViewer($viewer)
      ->withIDs(array($request->getURIData('id')))
      ->executeOne();
    if (!$source) {
      return new Aphront404Response();
    }

    $def = NuanceSourceDefinition::getDefinitionForSource($source);
    $def->setActor($viewer);

    $response = $def->handleActionRequest($request);
    if ($response instanceof AphrontResponse) {
      return $response;
    }

    $title = $source->getName();
    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb($title);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $response,
      ),
      array(
        'title' => $title,
      ));
  }

}
