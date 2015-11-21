<?php

final class PhabricatorEditEngineListController
  extends PhabricatorEditEngineController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    return id(new PhabricatorEditEngineSearchEngine())
      ->setController($this)
      ->buildResponse();
  }

}
