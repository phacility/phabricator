<?php

final class ConpherenceRoomEditController
  extends ConpherenceController {

  public function handleRequest(AphrontRequest $request) {
    return id(new ConpherenceEditEngine())
      ->setController($this)
      ->buildResponse();
  }
}
