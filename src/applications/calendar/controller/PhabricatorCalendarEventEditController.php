<?php

final class PhabricatorCalendarEventEditController
  extends PhabricatorCalendarController {

  public function handleRequest(AphrontRequest $request) {
    return id(new PhabricatorCalendarEditEngine())
      ->setController($this)
      ->buildResponse();
  }

}
