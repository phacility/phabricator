<?php

final class PhortuneAccountEmailEditController
  extends PhortuneAccountController {

  public function handleRequest(AphrontRequest $request) {
    $engine = id(new PhortuneAccountEmailEditEngine())
      ->setController($this);

    if (!$request->getURIData('id')) {

      if (!$request->getURIData('accountID')) {
        return new Aphront404Response();
      }

      $response = $this->loadAccount();
      if ($response) {
        return $response;
      }

      $account = $this->getAccount();

      $engine->setAccount($account);
    }

    return $engine->buildResponse();
  }
}
