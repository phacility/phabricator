<?php

final class PhabricatorRoleTriggerListController
  extends PhabricatorRoleTriggerController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    return id(new PhabricatorRoleTriggerSearchEngine())
      ->setController($this)
      ->buildResponse();
  }

}
