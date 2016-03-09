<?php

final class HarbormasterPlanEditController extends HarbormasterPlanController {

  public function handleRequest(AphrontRequest $request) {
    return id(new HarbormasterBuildPlanEditEngine())
      ->setController($this)
      ->buildResponse();
  }

}
