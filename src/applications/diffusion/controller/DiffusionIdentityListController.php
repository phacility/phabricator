<?php

final class DiffusionIdentityListController
  extends DiffusionController {

  public function handleRequest(AphrontRequest $request) {
    return id(new DiffusionRepositoryIdentitySearchEngine())
      ->setController($this)
      ->buildResponse();
  }

  protected function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    id(new PhabricatorRepositoryIdentityEditEngine())
      ->setViewer($this->getViewer())
      ->addActionToCrumbs($crumbs);

    return $crumbs;
  }

}
