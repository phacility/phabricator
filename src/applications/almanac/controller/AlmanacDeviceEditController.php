<?php

final class AlmanacDeviceEditController
  extends AlmanacDeviceController {

  public function handleRequest(AphrontRequest $request) {
    return id(new AlmanacDeviceEditEngine())
      ->setController($this)
      ->buildResponse();
  }

}
