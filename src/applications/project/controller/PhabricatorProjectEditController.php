<?php

final class PhabricatorProjectEditController
  extends PhabricatorProjectController {

  public function handleRequest(AphrontRequest $request) {
    return id(new PhabricatorProjectEditEngine())
      ->setController($this)
      ->buildResponse();
  }

}
