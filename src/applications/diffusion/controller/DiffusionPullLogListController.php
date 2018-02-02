<?php

final class DiffusionPullLogListController
  extends DiffusionLogController {

  public function handleRequest(AphrontRequest $request) {
    return id(new DiffusionPullLogSearchEngine())
      ->setController($this)
      ->buildResponse();
  }

  protected function buildApplicationCrumbs() {
    return parent::buildApplicationCrumbs()
      ->addTextCrumb(pht('Pull Logs'), $this->getApplicationURI('pulllog/'));
  }

}
