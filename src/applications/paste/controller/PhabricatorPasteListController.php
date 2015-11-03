<?php

final class PhabricatorPasteListController extends PhabricatorPasteController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    return id(new PhabricatorPasteSearchEngine())
      ->setController($this)
      ->buildResponse();
  }

  protected function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    $crumbs->addAction(
      id(new PHUIListItemView())
        ->setName(pht('Create Paste'))
        ->setHref($this->getApplicationURI('edit/'))
        ->setIcon('fa-plus-square'));

    return $crumbs;
  }

}
