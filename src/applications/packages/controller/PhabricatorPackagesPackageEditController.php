<?php

final class PhabricatorPackagesPackageEditController
  extends PhabricatorPackagesPackageController {

  public function handleRequest(AphrontRequest $request) {
    return id(new PhabricatorPackagesPackageEditEngine())
      ->setController($this)
      ->buildResponse();
  }

}
