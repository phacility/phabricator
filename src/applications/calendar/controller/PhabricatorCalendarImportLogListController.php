<?php

final class PhabricatorCalendarImportLogListController
  extends PhabricatorCalendarController {

  public function handleRequest(AphrontRequest $request) {
    return id(new PhabricatorCalendarImportLogSearchEngine())
      ->setController($this)
      ->buildResponse();
  }

}
