<?php

final class PhabricatorCalendarImportListController
  extends PhabricatorCalendarController {

  public function handleRequest(AphrontRequest $request) {
    return id(new PhabricatorCalendarImportSearchEngine())
      ->setController($this)
      ->buildResponse();
  }

}
