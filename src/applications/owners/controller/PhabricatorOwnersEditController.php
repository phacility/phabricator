<?php

final class PhabricatorOwnersEditController
  extends PhabricatorOwnersController {

  public function handleRequest(AphrontRequest $request) {
    return id(new PhabricatorOwnersPackageEditEngine())
      ->setController($this)
      ->buildResponse();
  }

}
