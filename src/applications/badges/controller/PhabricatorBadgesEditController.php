<?php

final class PhabricatorBadgesEditController extends PhabricatorPasteController {

  public function handleRequest(AphrontRequest $request) {
    return id(new PhabricatorBadgesEditEngine())
      ->setController($this)
      ->buildResponse();
  }

}
