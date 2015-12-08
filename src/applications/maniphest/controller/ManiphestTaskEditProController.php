<?php

final class ManiphestTaskEditProController extends ManiphestController {

  public function handleRequest(AphrontRequest $request) {
    return id(new ManiphestEditEngine())
      ->setController($this)
      ->buildResponse();
  }

}
