<?php

final class PhabricatorFileListController extends PhabricatorFileController {

  public function shouldAllowPublic() {
    return true;
  }

  public function isGlobalDragAndDropUploadEnabled() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    return id(new PhabricatorFileSearchEngine())
      ->setController($this)
      ->buildResponse();
  }

  protected function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    $crumbs->addAction(
      id(new PHUIListItemView())
        ->setName(pht('Upload File'))
        ->setIcon('fa-upload')
        ->setHref($this->getApplicationURI('/upload/')));

    return $crumbs;
  }

}
