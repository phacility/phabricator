<?php

final class PhameBlog404Controller extends PhameLiveController {

  public function handleRequest(AphrontRequest $request) {
    $response = $this->setupLiveEnvironment();
    if ($response) {
      return $response;
    }

    return new Aphront404Response();
  }

}
