<?php

final class PhabricatorCalendarEventJoinController
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

    $action = $request->getURIData('action');
    switch ($action) {
      case 'accept':
        $is_join = true;
        break;
      case 'decline':
        $is_join = false;
        break;
      default:
        $is_join = !$event->getIsUserAttending($viewer->getPHID());
        break;
    }

    $validation_exception = null;
    if ($request->isFormPost()) {
      if ($is_join) {
        $xaction_type =
          PhabricatorCalendarEventAcceptTransaction::TRANSACTIONTYPE;
      } else {
        $xaction_type =
          PhabricatorCalendarEventDeclineTransaction::TRANSACTIONTYPE;
      }

      $xaction = id(new PhabricatorCalendarEventTransaction())
        ->setTransactionType($xaction_type)
        ->setNewValue(true);

      $editor = id(new PhabricatorCalendarEventEditor())
        ->setActor($viewer)
        ->setContentSourceFromRequest($request)
        ->setContinueOnNoEffect(true)
        ->setContinueOnMissingFields(true);

      try {
        $editor->applyTransactions($event, array($xaction));
        return id(new AphrontRedirectResponse())->setURI($cancel_uri);
      } catch (PhabricatorApplicationTransactionValidationException $ex) {
        $validation_exception = $ex;
      }
    }

    if ($is_join) {
      $title = pht('Join Event');
      $paragraph = pht('Would you like to join this event?');
      $submit = pht('Join');
    } else {
      $title = pht('Decline Event');
      $paragraph = pht('Would you like to decline this event?');
      $submit = pht('Decline');
    }

    return $this->newDialog()
      ->setTitle($title)
      ->setValidationException($validation_exception)
      ->appendParagraph($paragraph)
      ->addCancelButton($cancel_uri)
      ->addSubmitButton($submit);
  }
}
