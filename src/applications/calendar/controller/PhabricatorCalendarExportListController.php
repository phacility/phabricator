<?php

final class PhabricatorCalendarExportListController
  extends PhabricatorCalendarController {

  public function handleRequest(AphrontRequest $request) {
    return id(new PhabricatorCalendarExportSearchEngine())
      ->setController($this)
      ->buildResponse();
  }

  protected function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    $doc_name = 'Calendar User Guide: Exporting Events';
    $doc_href = PhabricatorEnv::getDoclink($doc_name);

    $crumbs->addAction(
      id(new PHUIListItemView())
        ->setName(pht('Guide: Exporting Events'))
        ->setIcon('fa-book')
        ->setHref($doc_href));

    return $crumbs;
  }

}
