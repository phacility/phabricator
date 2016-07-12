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

    id(new PhabricatorPasteEditEngine())
      ->setViewer($this->getViewer())
      ->addActionToCrumbs($crumbs);

    return $crumbs;
  }

}
