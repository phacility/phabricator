<?php

final class PhabricatorAuthContactNumberEditController
  extends PhabricatorAuthContactNumberController {

  public function handleRequest(AphrontRequest $request) {
    return id(new PhabricatorAuthContactNumberEditEngine())
      ->setController($this)
      ->buildResponse();
  }

}
