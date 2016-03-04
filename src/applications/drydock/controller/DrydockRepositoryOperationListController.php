<?php

final class DrydockRepositoryOperationListController
  extends DrydockRepositoryOperationController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    return id(new DrydockRepositoryOperationSearchEngine())
      ->setController($this)
      ->buildResponse();
  }

}
