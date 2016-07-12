<?php

final class PhabricatorConduitLogController
  extends PhabricatorConduitController {

  public function handleRequest(AphrontRequest $request) {
    return id(new PhabricatorConduitLogSearchEngine())
      ->setController($this)
      ->buildResponse();
  }

}
