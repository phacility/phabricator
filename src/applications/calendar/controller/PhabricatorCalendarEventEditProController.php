<?php

final class PhabricatorCalendarEventEditProController
  extends PhabricatorCalendarController {

  public function handleRequest(AphrontRequest $request) {
    return id(new PhabricatorCalendarEditEngine())
      ->setController($this)
      ->buildResponse();
  }

}
