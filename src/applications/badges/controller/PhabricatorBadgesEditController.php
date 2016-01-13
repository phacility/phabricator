<?php

final class PhabricatorBadgesEditController extends
  PhabricatorBadgesController {
  public function handleRequest(AphrontRequest $request) {
    return id(new PhabricatorBadgesEditEngine())
      ->setController($this)
      ->buildResponse();
  }

}
