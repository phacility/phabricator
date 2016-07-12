<?php

final class PholioMockListController extends PholioController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    return id(new PholioMockSearchEngine())
      ->setController($this)
      ->buildResponse();
  }

}
