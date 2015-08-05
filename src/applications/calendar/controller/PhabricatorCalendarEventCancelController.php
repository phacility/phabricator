<?php

final class PhabricatorCalendarEventCancelController
  extends PhabricatorCalendarController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');
    $sequence = $request->getURIData('sequence');

    $event = id(new PhabricatorCalendarEventQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();

    if ($sequence) {
      $parent_event = $event;
      $event = $parent_event->generateNthGhost($sequence, $viewer);
      $event->attachParentEvent($parent_event);
    }

    if (!$event) {
      return new Aphront404Response();
    }

    if (!$sequence) {
      $cancel_uri = '/E'.$event->getID();
    } else {
      $cancel_uri = '/E'.$event->getID().'/'.$sequence;
    }

    $is_cancelled = $event->getIsCancelled();
    $is_parent_cancelled = $event->getIsParentCancelled();
    $is_parent = $event->getIsRecurrenceParent();

    $validation_exception = null;

    if ($request->isFormPost()) {
      if ($is_cancelled && $sequence) {
        return id(new AphrontRedirectResponse())->setURI($cancel_uri);
      } else if ($sequence) {
        $event = $this->createEventFromGhost(
          $viewer,
          $event,
          $sequence);
        $event->applyViewerTimezone($viewer);
      }

      $xactions = array();

      $xaction = id(new PhabricatorCalendarEventTransaction())
        ->setTransactionType(
          PhabricatorCalendarEventTransaction::TYPE_CANCEL)
        ->setNewValue(!$is_cancelled);

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

    if ($is_cancelled) {
      if ($sequence || $is_parent_cancelled) {
        $title = pht('Cannot Reinstate Instance');
        $paragraph = pht(
          'Cannot reinstate an instance of a cancelled recurring event.');
        $cancel = pht('Cancel');
        $submit = null;
      } else if ($is_parent) {
        $title = pht('Reinstate Recurrence');
        $paragraph = pht(
          'Reinstate the entire series of recurring events?');
        $cancel = pht("Don't Reinstate Recurrence");
        $submit = pht('Reinstate Recurrence');
      } else {
        $title = pht('Reinstate Event');
        $paragraph = pht('Reinstate this event?');
        $cancel = pht("Don't Reinstate Event");
        $submit = pht('Reinstate Event');
      }
    } else {
      if ($sequence) {
        $title = pht('Cancel Instance');
        $paragraph = pht(
          'Cancel just this instance of a recurring event.');
        $cancel = pht("Don't Cancel Instance");
        $submit = pht('Cancel Instance');
      } else if ($is_parent) {
        $title = pht('Cancel Recurrence');
        $paragraph = pht(
          'Cancel the entire series of recurring events?');
        $cancel = pht("Don't Cancel Recurrence");
        $submit = pht('Cancel Recurrence');
      } else {
        $title = pht('Cancel Event');
        $paragraph = pht(
          'You can always reinstate the event later.');
        $cancel = pht("Don't Cancel Event");
        $submit = pht('Cancel Event');
      }
    }

    return $this->newDialog()
      ->setTitle($title)
      ->setValidationException($validation_exception)
      ->appendParagraph($paragraph)
      ->addCancelButton($cancel_uri, $cancel)
      ->addSubmitButton($submit);
  }
}
