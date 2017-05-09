<?php

final class FundInitiativeEditController extends
  FundController {
  public function handleRequest(AphrontRequest $request) {
    return id(new FundInitiativeEditEngine())
      ->setController($this)
      ->buildResponse();
  }

}
