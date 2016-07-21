<?php

final class PhabricatorPackagesPublisherEditController
  extends PhabricatorPackagesPublisherController {

  public function handleRequest(AphrontRequest $request) {
    return id(new PhabricatorPackagesPublisherEditEngine())
      ->setController($this)
      ->buildResponse();
  }

}
