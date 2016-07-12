<?php

final class PhabricatorOwnersListController
  extends PhabricatorOwnersController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    return id(new PhabricatorOwnersPackageSearchEngine())
      ->setController($this)
      ->buildResponse();
  }

  protected function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    id(new PhabricatorOwnersPackageEditEngine())
      ->setViewer($this->getViewer())
      ->addActionToCrumbs($crumbs);

    return $crumbs;
  }

}
