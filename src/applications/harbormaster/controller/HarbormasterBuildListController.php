<?php

final class HarbormasterBuildListController extends HarbormasterController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    return id(new HarbormasterBuildSearchEngine())
      ->setController($this)
      ->buildResponse();
  }

}
