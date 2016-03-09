<?php

final class NuanceQueueEditController
  extends NuanceQueueController {

  public function handleRequest(AphrontRequest $request) {
    return id(new NuanceQueueEditEngine())
      ->setController($this)
      ->buildResponse();
  }

}
