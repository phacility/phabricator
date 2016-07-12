<?php

final class DiffusionPushLogListController extends DiffusionPushLogController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    return id(new PhabricatorRepositoryPushLogSearchEngine())
      ->setController($this)
      ->buildResponse();
  }

}
