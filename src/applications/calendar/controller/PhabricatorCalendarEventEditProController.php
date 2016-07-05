<?php

final class PhabricatorCalendarEventEditProController
  extends ManiphestController {

  public function handleRequest(AphrontRequest $request) {
    return id(new PhabricatorCalendarEditEngine())
      ->setController($this)
      ->buildResponse();
  }

}
