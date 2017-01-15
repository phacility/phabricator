<?php

final class PhabricatorCalendarEventAvailabilityController
  extends PhabricatorCalendarController {

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

    $response = $this->newImportedEventResponse($event);
    if ($response) {
      return $response;
    }

    $cancel_uri = $event->getURI();

    if (!$event->getIsUserAttending($viewer->getPHID())) {
      return $this->newDialog()
        ->setTitle(pht('Not Attending Event'))
        ->appendParagraph(
          pht(
            'You can not change your display availability for events you '.
            'are not attending.'))
        ->addCancelButton($cancel_uri);
    }

    // TODO: This endpoint currently only works via AJAX. It would be vaguely
    // nice to provide a plain HTML version of the workflow where we return
    // a dialog with a vanilla <select /> in it for cases where all the JS
    // breaks.
    $request->validateCSRF();

    $invitee = $event->getInviteeForPHID($viewer->getPHID());

    $map = PhabricatorCalendarEventInvitee::getAvailabilityMap();
    $new_availability = $request->getURIData('availability');
    if (isset($map[$new_availability])) {
      $invitee
        ->setAvailability($new_availability)
        ->save();

      // Invalidate the availability cache.
      $viewer->writeAvailabilityCache(array(), null);
    }

    return id(new AphrontRedirectResponse())->setURI($cancel_uri);
  }
}
