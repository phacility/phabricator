<?php

final class NuanceQueueListController
  extends NuanceQueueController {

  public function handleRequest(AphrontRequest $request) {
    return id(new NuanceQueueSearchEngine())
      ->setController($this)
      ->buildResponse();
  }

  protected function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    id(new NuanceQueueEditEngine())
      ->setViewer($this->getViewer())
      ->addActionToCrumbs($crumbs);

    return $crumbs;
  }

}
