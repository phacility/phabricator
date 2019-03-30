<?php

final class ManiphestTaskEditController extends ManiphestController {

  public function handleRequest(AphrontRequest $request) {
    return id(new ManiphestEditEngine())
      ->setController($this)
      ->addContextParameter('responseType')
      ->addContextParameter('columnPHID')
      ->addContextParameter('order')
      ->addContextParameter('visiblePHIDs')
      ->buildResponse();
  }

}
