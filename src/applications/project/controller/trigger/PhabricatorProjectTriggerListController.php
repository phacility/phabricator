<?php

final class PhabricatorProjectTriggerListController
  extends PhabricatorProjectTriggerController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    return id(new PhabricatorProjectTriggerSearchEngine())
      ->setController($this)
      ->buildResponse();
  }

}
