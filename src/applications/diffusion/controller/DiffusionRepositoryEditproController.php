<?php

final class DiffusionRepositoryEditproController
  extends DiffusionRepositoryEditController {

  public function handleRequest(AphrontRequest $request) {
    return id(new DiffusionRepositoryEditEngine())
      ->setController($this)
      ->buildResponse();
  }

}
