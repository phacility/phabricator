<?php

final class PonderQuestionEditController extends
  PonderController {
  public function handleRequest(AphrontRequest $request) {
    return id(new PonderQuestionEditEngine())
      ->setController($this)
      ->buildResponse();
  }

}
