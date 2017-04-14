<?php

final class PhortuneAccountEditController extends
  PhortuneController {

  public function handleRequest(AphrontRequest $request) {
    return id(new PhortuneAccountEditEngine())
      ->setController($this)
      ->buildResponse();
  }
}
