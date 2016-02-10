<?php

final class PhabricatorCountdownListController
  extends PhabricatorCountdownController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    return id(new PhabricatorCountdownSearchEngine())
      ->setController($this)
      ->buildResponse();
  }

}
