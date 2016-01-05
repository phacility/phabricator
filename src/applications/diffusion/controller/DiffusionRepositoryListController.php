<?php

final class DiffusionRepositoryListController extends DiffusionController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    return id(new PhabricatorRepositorySearchEngine())
      ->setController($this)
      ->buildResponse();
  }

  protected function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    $can_create = $this->hasApplicationCapability(
      DiffusionCreateRepositoriesCapability::CAPABILITY);

    $crumbs->addAction(
      id(new PHUIListItemView())
        ->setName(pht('New Repository'))
        ->setHref($this->getApplicationURI('new/'))
        ->setDisabled(!$can_create)
        ->setWorkflow(!$can_create)
        ->setIcon('fa-plus-square'));

    return $crumbs;
  }

}
