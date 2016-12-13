<?php

final class DifferentialRevisionEditProController
  extends DifferentialController {

  public function handleRequest(AphrontRequest $request) {
    return id(new DifferentialRevisionEditEngine())
      ->setController($this)
      ->buildResponse();
  }

}
