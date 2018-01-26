<?php

final class DiffusionPushLogListController
  extends DiffusionLogController {

  public function handleRequest(AphrontRequest $request) {
    return id(new PhabricatorRepositoryPushLogSearchEngine())
      ->setController($this)
      ->buildResponse();
  }

}
