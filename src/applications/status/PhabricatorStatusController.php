<?php

final class PhabricatorStatusController extends PhabricatorController {

  public function shouldRequireLogin() {
    return false;
  }

  public function processRequest() {
    $response = new AphrontWebpageResponse();
    $response->setContent("ALIVE\n");
    return $response;
  }
}
