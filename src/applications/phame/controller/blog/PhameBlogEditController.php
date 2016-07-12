<?php

final class PhameBlogEditController extends PhameBlogController {

  public function handleRequest(AphrontRequest $request) {
    return id(new PhameBlogEditEngine())
      ->setController($this)
      ->buildResponse();
  }

}
