<?php

final class LegalpadDocumentEditController extends LegalpadController {

  public function handleRequest(AphrontRequest $request) {
    return id(new LegalpadDocumentEditEngine())
      ->setController($this)
      ->buildResponse();
  }

}
