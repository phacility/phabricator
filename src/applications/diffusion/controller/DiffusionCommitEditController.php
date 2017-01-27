<?php

final class DiffusionCommitEditController
  extends DiffusionController {

  public function handleRequest(AphrontRequest $request) {
    return id(new DiffusionCommitEditEngine())
      ->setController($this)
      ->buildResponse();
  }

}
