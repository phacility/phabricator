<?php

final class AlmanacNamespaceEditController extends AlmanacNamespaceController {

  public function handleRequest(AphrontRequest $request) {
    return id(new AlmanacNamespaceEditEngine())
      ->setController($this)
      ->buildResponse();
  }

}
