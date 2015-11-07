<?php

final class PhabricatorPasteEditController extends PhabricatorPasteController {

  public function handleRequest(AphrontRequest $request) {
    return id(new PhabricatorPasteEditEngine())
      ->setController($this)
      ->buildResponse();
  }

}
