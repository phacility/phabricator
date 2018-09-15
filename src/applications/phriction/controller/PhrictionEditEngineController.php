<?php

final class PhrictionEditEngineController
  extends PhrictionController {

  public function handleRequest(AphrontRequest $request) {
    // NOTE: For now, this controller is only used to handle comments.

    return id(new PhrictionDocumentEditEngine())
      ->setController($this)
      ->buildResponse();
  }

}
