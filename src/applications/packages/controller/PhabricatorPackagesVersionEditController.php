<?php

final class PhabricatorPackagesVersionEditController
  extends PhabricatorPackagesVersionController {

  public function handleRequest(AphrontRequest $request) {
    return id(new PhabricatorPackagesVersionEditEngine())
      ->setController($this)
      ->buildResponse();
  }

}
