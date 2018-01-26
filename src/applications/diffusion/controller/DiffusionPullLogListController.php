<?php

final class DiffusionPullLogListController
  extends DiffusionLogController {

  public function handleRequest(AphrontRequest $request) {
    return id(new DiffusionPullLogSearchEngine())
      ->setController($this)
      ->buildResponse();
  }

}
