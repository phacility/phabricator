<?php

final class PhabricatorCalendarImportListController
  extends PhabricatorCalendarController {

  public function handleRequest(AphrontRequest $request) {
    return id(new PhabricatorCalendarImportSearchEngine())
      ->setController($this)
      ->buildResponse();
  }

  protected function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    $crumbs->addAction(
      id(new PHUIListItemView())
        ->setName(pht('Import Events'))
        ->setHref($this->getApplicationURI('import/edit/'))
        ->setIcon('fa-upload'));

    return $crumbs;
  }


}
