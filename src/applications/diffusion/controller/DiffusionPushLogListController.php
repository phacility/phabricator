<?php

final class DiffusionPushLogListController
  extends DiffusionLogController {

  public function handleRequest(AphrontRequest $request) {
    return id(new PhabricatorRepositoryPushLogSearchEngine())
      ->setController($this)
      ->buildResponse();
  }

  protected function buildApplicationCrumbs() {
    return parent::buildApplicationCrumbs()
      ->addTextCrumb(pht('Push Logs'), $this->getApplicationURI('pushlog/'));
  }

}
