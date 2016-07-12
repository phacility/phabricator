<?php

final class PhabricatorCalendarEventJoinController
  extends PhabricatorCalendarController {

  const ACTION_ACCEPT = 'accept';
  const ACTION_DECLINE = 'decline';
  const ACTION_JOIN = 'join';

  public function handleRequest(AphrontRequest $request) {
    $id = $request->getURIData('id');
    $action = $request->getURIData('action');

    $request = $this->getRequest();
    $viewer = $request->getViewer();
    $declined_status = PhabricatorCalendarEventInvitee::STATUS_DECLINED;
    $attending_status = PhabricatorCalendarEventInvitee::STATUS_ATTENDING;

    $event = id(new PhabricatorCalendarEventQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->executeOne();

    if (!$event) {
      return new Aphront404Response();
    }

    $cancel_uri = '/E'.$event->getID();
    $validation_exception = null;

    $is_attending = $event->getIsUserAttending($viewer->getPHID());

    if ($request->isFormPost()) {
      $new_status = null;

      switch ($action) {
        case self::ACTION_ACCEPT:
          $new_status = $attending_status;
          break;
        case self::ACTION_JOIN:
          if ($is_attending) {
            $new_status = $declined_status;
          } else {
            $new_status = $attending_status;
          }
          break;
        case self::ACTION_DECLINE:
          $new_status = $declined_status;
          break;
      }

      $new_status = array($viewer->getPHID() => $new_status);

      $xaction = id(new PhabricatorCalendarEventTransaction())
        ->setTransactionType(PhabricatorCalendarEventTransaction::TYPE_INVITE)
        ->setNewValue($new_status);

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

    if (($action == self::ACTION_JOIN && !$is_attending)
      || $action == self::ACTION_ACCEPT) {
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
