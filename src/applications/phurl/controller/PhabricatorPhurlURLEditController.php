<?php

final class PhabricatorPhurlURLEditController
  extends PhabricatorPhurlController {

  public function handleRequest(AphrontRequest $request) {
    return id(new PhabricatorPhurlURLEditEngine())
      ->setController($this)
      ->buildResponse();
  }
}
