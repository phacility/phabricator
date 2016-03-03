<?php

final class DrydockBlueprintListController extends DrydockBlueprintController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    return id(new DrydockBlueprintSearchEngine())
      ->setController($this)
      ->buildResponse();
  }

  protected function buildApplicationCrumbs() {
    $can_create = $this->hasApplicationCapability(
      DrydockCreateBlueprintsCapability::CAPABILITY);

    $crumbs = parent::buildApplicationCrumbs();
    $crumbs->addAction(
      id(new PHUIListItemView())
        ->setName(pht('New Blueprint'))
        ->setHref($this->getApplicationURI('/blueprint/create/'))
        ->setDisabled(!$can_create)
        ->setWorkflow(!$can_create)
        ->setIcon('fa-plus-square'));
    return $crumbs;
  }

}
