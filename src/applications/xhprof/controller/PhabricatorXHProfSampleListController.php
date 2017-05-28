<?php

final class PhabricatorXHProfSampleListController
  extends PhabricatorXHProfController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    return id(new PhabricatorXHProfSampleSearchEngine())
      ->setController($this)
      ->buildResponse();
  }

}
