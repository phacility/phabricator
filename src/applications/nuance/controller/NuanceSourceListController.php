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

    $can_create = $this->hasApplicationCapability(
      NuanceSourceManageCapability::CAPABILITY);

    $crumbs->addAction(
      id(new PHUIListItemView())
        ->setName(pht('Create Source'))
        ->setHref($this->getApplicationURI('source/create/'))
        ->setIcon('fa-plus-square')
        ->setDisabled(!$can_create)
        ->setWorkflow(!$can_create));

    return $crumbs;
  }

}
