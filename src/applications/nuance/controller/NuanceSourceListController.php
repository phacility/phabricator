<?php

final class NuanceSourceListController
  extends NuanceSourceController {

  public function handleRequest(AphrontRequest $request) {
    return id(new NuanceSourceSearchEngine())
      ->setController($this)
      ->buildResponse();
  }

  protected function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    id(new NuanceSourceEditEngine())
      ->setViewer($this->getViewer())
      ->addActionToCrumbs($crumbs);

    return $crumbs;
  }

}
