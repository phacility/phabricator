<?php

final class DiffusionSyncLogListController
  extends DiffusionLogController {

  public function handleRequest(AphrontRequest $request) {
    return id(new DiffusionSyncLogSearchEngine())
      ->setController($this)
      ->buildResponse();
  }

  protected function buildApplicationCrumbs() {
    return parent::buildApplicationCrumbs()
      ->addTextCrumb(pht('Sync Logs'), $this->getApplicationURI('synclog/'));
  }

}
