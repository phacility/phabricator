<?php

final class PhabricatorCalendarEventExportController
  extends PhabricatorCalendarController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();
    $id = $request->getURIData('id');

    $event = id(new PhabricatorCalendarEventQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->executeOne();
    if (!$event) {
      return new Aphront404Response();
    }

    if ($event->isChildEvent()) {
      $target = $event->getParentEvent();
    } else {
      $target = $event;
    }

    return $this->newICSResponse(
      $viewer,
      $target->getICSFileName(),
      array($target));
  }

}
