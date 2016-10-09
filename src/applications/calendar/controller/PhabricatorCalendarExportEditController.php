<?php

final class PhabricatorCalendarExportEditController
  extends PhabricatorCalendarController {

  public function handleRequest(AphrontRequest $request) {
    return id(new PhabricatorCalendarExportEditEngine())
      ->setController($this)
      ->buildResponse();
  }

}
