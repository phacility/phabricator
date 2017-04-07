<?php

final class PhabricatorFileEditController
  extends PhabricatorFileController {

  public function handleRequest(AphrontRequest $request) {
    return id(new PhabricatorFileEditEngine())
      ->setController($this)
      ->buildResponse();
  }

}
