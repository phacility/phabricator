<?php

final class PhabricatorDashboardEditController
  extends PhabricatorDashboardController {

  public function handleRequest(AphrontRequest $request) {
    return id(new PhabricatorDashboardEditEngine())
      ->setController($this)
      ->buildResponse();
  }

}
