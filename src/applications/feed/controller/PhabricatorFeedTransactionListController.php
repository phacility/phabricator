<?php

final class PhabricatorFeedTransactionListController
  extends PhabricatorFeedController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    return id(new PhabricatorFeedTransactionSearchEngine())
      ->setController($this)
      ->buildResponse();
  }

}
