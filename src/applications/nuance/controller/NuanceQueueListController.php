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

    // TODO: Maybe use SourceManage capability?
    $can_create = true;

    $crumbs->addAction(
      id(new PHUIListItemView())
        ->setName(pht('Create Queue'))
        ->setHref($this->getApplicationURI('queue/new/'))
        ->setIcon('fa-plus-square')
        ->setDisabled(!$can_create)
        ->setWorkflow(!$can_create));

    return $crumbs;
  }

}
