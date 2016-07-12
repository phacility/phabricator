<?php

final class ManiphestTaskEditController extends ManiphestController {

  public function handleRequest(AphrontRequest $request) {
    return id(new ManiphestEditEngine())
      ->setController($this)
      ->addContextParameter('ungrippable')
      ->addContextParameter('responseType')
      ->addContextParameter('columnPHID')
      ->addContextParameter('order')
      ->addContextParameter('visiblePHIDs')
      ->buildResponse();
  }

}
