<?php

final class PhameBlogListController extends PhameBlogController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    return id(new PhameBlogSearchEngine())
      ->setController($this)
      ->buildResponse();
  }


  protected function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    id(new PhameBlogEditEngine())
      ->setViewer($this->getViewer())
      ->addActionToCrumbs($crumbs);

    return $crumbs;
  }

}
