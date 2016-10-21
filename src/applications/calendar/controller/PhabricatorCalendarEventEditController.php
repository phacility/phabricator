<?php

final class PhabricatorCalendarEventEditController
  extends PhabricatorCalendarController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    $id = $request->getURIData('id');
    if ($id) {
      $event = id(new PhabricatorCalendarEventQuery())
        ->setViewer($viewer)
        ->withIDs(array($id))
        ->executeOne();
      $response = $this->newImportedEventResponse($event);
      if ($response) {
        return $response;
      }
    }

    return id(new PhabricatorCalendarEventEditEngine())
      ->setController($this)
      ->buildResponse();
  }

}
