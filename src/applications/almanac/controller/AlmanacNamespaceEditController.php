<?php

final class AlmanacNamespaceEditController extends AlmanacController {

  public function handleRequest(AphrontRequest $request) {
    return id(new AlmanacNamespaceEditEngine())
      ->setController($this)
      ->buildResponse();
  }

}
