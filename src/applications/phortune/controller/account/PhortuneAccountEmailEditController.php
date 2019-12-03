<?php

final class PhortuneAccountEmailEditController
  extends PhortuneAccountController {

  protected function shouldRequireAccountEditCapability() {
    return true;
  }

  protected function handleAccountRequest(AphrontRequest $request) {
    $account = $this->getAccount();

    $engine = id(new PhortuneAccountEmailEditEngine())
      ->setController($this);

    if (!$request->getURIData('id')) {
      $engine->setAccount($account);
    }

    return $engine->buildResponse();
  }
}
