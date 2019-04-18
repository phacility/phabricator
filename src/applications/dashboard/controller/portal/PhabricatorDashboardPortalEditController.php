<?php

final class PhabricatorDashboardPortalEditController
  extends PhabricatorDashboardPortalController {

  public function handleRequest(AphrontRequest $request) {
    return id(new PhabricatorDashboardPortalEditEngine())
      ->setController($this)
      ->buildResponse();
  }

}
