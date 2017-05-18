<?php

final class PhabricatorMacroEditController extends PhameBlogController {

  public function handleRequest(AphrontRequest $request) {
    return id(new PhabricatorMacroEditEngine())
      ->setController($this)
      ->buildResponse();
  }
}
