<?php

final class PhabricatorRefreshCSRFController extends PhabricatorAuthController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    return id(new AphrontAjaxResponse())
      ->setContent(
        array(
          'token' => $viewer->getCSRFToken(),
        ));
  }

}
