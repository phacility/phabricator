<?php

final class DivinerAtomListController extends DivinerController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    return id(new DivinerAtomSearchEngine())
      ->setController($this)
      ->buildResponse();
  }

}
