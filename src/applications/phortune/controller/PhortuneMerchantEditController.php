<?php

final class PhortuneMerchantEditController
  extends PhortuneMerchantController {

  public function handleRequest(AphrontRequest $request) {
    return id(new PhortuneMerchantEditEngine())
      ->setController($this)
      ->buildResponse();
  }
}
