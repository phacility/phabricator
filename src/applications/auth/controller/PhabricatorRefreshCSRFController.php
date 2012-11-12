<?php

final class PhabricatorRefreshCSRFController extends PhabricatorAuthController {

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    return id(new AphrontAjaxResponse())
      ->setContent(
        array(
          'token' => $user->getCSRFToken(),
        ));
  }

}
