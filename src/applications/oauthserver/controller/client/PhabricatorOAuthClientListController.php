<?php

final class PhabricatorOAuthClientListController
  extends PhabricatorOAuthClientController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    return id(new PhabricatorOAuthServerClientSearchEngine())
      ->setController($this)
      ->buildResponse();
  }

  protected function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    id(new PhabricatorOAuthServerEditEngine())
      ->setViewer($this->getViewer())
      ->addActionToCrumbs($crumbs);

    return $crumbs;
  }

}
