<?php

final class PhabricatorOAuthClientEditController
  extends PhabricatorOAuthClientController {

  public function handleRequest(AphrontRequest $request) {
    return id(new PhabricatorOAuthServerEditEngine())
      ->setController($this)
      ->buildResponse();
  }

}
