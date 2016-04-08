<?php

final class PhabricatorCountdownEditController
  extends PhabricatorCountdownController {

  public function handleRequest(AphrontRequest $request) {
    return id(new PhabricatorCountdownEditEngine())
      ->setController($this)
      ->buildResponse();
  }

}
