<?php

final class AlmanacNetworkEditController extends AlmanacNetworkController {

  public function handleRequest(AphrontRequest $request) {
    return id(new AlmanacNetworkEditEngine())
      ->setController($this)
      ->buildResponse();
  }

}
