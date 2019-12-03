<?php

final class PhortuneMerchantEditController
  extends PhortuneController {

  public function handleRequest(AphrontRequest $request) {
    return id(new PhortuneMerchantEditEngine())
      ->setController($this)
      ->buildResponse();
  }
}
