<?php

final class DiffusionIdentityEditController
  extends DiffusionController {

  public function handleRequest(AphrontRequest $request) {
    return id(new PhabricatorRepositoryIdentityEditEngine())
      ->setController($this)
      ->buildResponse();
  }

}
