<?php

final class PhabricatorPhurlShortURLDefaultController
  extends PhabricatorPhurlController {

  public function shouldRequireLogin() {
    return false;
  }

  public function handleRequest(AphrontRequest $request) {
    return new Aphront404Response();
  }
}
